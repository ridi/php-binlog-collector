<?php

namespace Binlog\Collector\Model;

use Binlog\Collector\Dto\BinlogHistoryDto;
use Illuminate\Support\Collection;

/**
 * Class OnceBinlogHistoryModel
 * @package Binlog\Collector\Model
 */
class OnceBinlogHistoryModel extends BinlogHistoryBaseModel
{
	/**
	 * insert Universal History Bulk
	 *
	 * @param BinlogHistoryDto[] $dtos
	 *
	 * @return int
	 */
	public function insertHistoryBulk(array $dtos): int
	{
		if (count($dtos) == 0) {
			return 0;
		}

		$dtos = collect($dtos);
		$dtos = $this->insertBinlogPosition($dtos);
		$dtos = $this->insertRows($dtos);
		$this->insertColumns($dtos);

		return count($dtos);
	}

	private function insertBinlogPosition(Collection $dtos): Collection
	{
		$key_to_binlog_id = [];
		$dtos = $dtos->map(
			function (BinlogHistoryDto $dto) use (&$key_to_binlog_id) {
				if ($dto->binlog_id !== null) {
					return $dto;
				}
				$key = $dto->findBinlogIdKeyVer3();
				if (array_key_exists($key, $key_to_binlog_id)) {
					$dto->binlog_id = $key_to_binlog_id[$key];
				} else {
					$dto->binlog_id = $this->getBinlogId($dto);
					$key_to_binlog_id[$key] = $dto->binlog_id;
				}

				return $dto;
			}
		);

		$binlog_infos = $dtos->map(
			function (BinlogHistoryDto $dto) {
				return $dto->exportBinlogInfoDatabaseVer3();
			}
		)->unique();

		$insert_binlog_infos = $binlog_infos->reject(
			function ($binlog_info) {
				return array_key_exists('id', $binlog_info);
			}
		);

		if ($insert_binlog_infos->count() > 0) {
			$keys = array_keys($insert_binlog_infos->first());
			$this->db->sqlInsertBulk(
				'platform_once_history_3_binlog',
				$keys,
				$insert_binlog_infos->all()
			);
		}

		$key_to_binlog_id = [];
		return $dtos->map(
			function (BinlogHistoryDto $dto) use (&$key_to_binlog_id) {
				if ($dto->binlog_id !== null) {
					return $dto;
				}
				$key = $dto->findBinlogIdKeyVer3();
				if (array_key_exists($key, $key_to_binlog_id)) {
					$dto->binlog_id = $key_to_binlog_id[$key];
				} else {
					$dto->binlog_id = $this->getBinlogId($dto);
					$key_to_binlog_id[$key] = $dto->binlog_id;
				}

				return $dto;
			}
		);
	}

	/**
	 * @param BinlogHistoryDto $dto
	 *
	 * @return string|null
	 */
	private function getBinlogId(BinlogHistoryDto $dto)
	{
		return $this->db->sqlData(
			'SELECT id FROM platform_once_history_3_binlog WHERE ?',
			sqlWhere($dto->exportBinlogInfoDatabaseVer3())
		);
	}

	private function insertRows(Collection $dtos): Collection
	{
		$dtos = $this->fillRowIds($dtos);
		$rows = $dtos->map(
			function (BinlogHistoryDto $dto) {
				return $dto->exportRowInfoDatabaseVer3();
			}
		)->unique();

		$insert_rows = $rows->reject(
			function ($row) {
				return array_key_exists('id', $row);
			}
		);

		if ($insert_rows->count() > 0) {
			$keys = array_keys($insert_rows->first());
			$this->db->sqlInsertBulk(
				'platform_once_history_3_row',
				$keys,
				$insert_rows->all()
			);
		}

		return $this->fillRowIds($dtos);
	}

	private function fillRowIds(Collection $dtos): Collection
	{
		$binlog_ids = $dtos->pluck('binlog_id')->unique()->all();
		$row_dicts = $this->getRowDictsByBinlogIds($binlog_ids);
		$key_to_row_dicts = collect($row_dicts)->keyBy(
			function ($row_dict) {
				//$dto->findRowIdKeyVer3();
				return $row_dict['binlog_id'] . $row_dict['idx'] . $row_dict['table_name'] . $row_dict['pk_ids'];
			}
		)->all();

		return $dtos->map(
			function (BinlogHistoryDto $dto) use ($key_to_row_dicts) {
				if ($dto->row_id !== null) {
					return $dto;
				}
				$key = $dto->findRowIdKeyVer3();
				if (array_key_exists($key, $key_to_row_dicts)) {
					$dto->row_id = $key_to_row_dicts[$key]['id'];
				}

				return $dto;
			}
		);
	}

	private function getRowDictsByBinlogIds(array $binlog_ids): array
	{
		$where = ['binlog_id' => $binlog_ids];

		return $this->db->sqlDicts(
			'SELECT id, binlog_id, idx, table_name, pk_ids FROM platform_once_history_3_row WHERE ?',
			sqlWhere($where)
		);
	}

	private function insertColumns(Collection $dtos)
	{
		$row_ids = $dtos->pluck('row_id')->unique()->all();
		$column_dicts = $this->getColumnDictsByRowIds($row_ids);
		$key_to_column_dicts = collect($column_dicts)->keyBy(
			function ($column_dict) {
				return $column_dict['row_id'] . $column_dict['column'];
			}
		)->all();
		$column_rows = $dtos
			->flatMap(
				function (BinlogHistoryDto $dto) {
					return $dto->exportColumnInfoWithRowIdDatabaseVer3();
				}
			)
			->reject(
				function ($dict) use ($key_to_column_dicts) {
					$key = $dict['row_id'] . $dict['column'];
					$column_id = null;
					if (array_key_exists($key, $key_to_column_dicts)) {
						$column_id = $key_to_column_dicts[$key]['id'];
					}

					return $column_id !== null;
				}
			);

		if ($column_rows->count() > 0) {
			$keys = array_keys($column_rows->first());
			$this->db->sqlInsertBulk(
				'platform_once_history_3_column',
				$keys,
				$column_rows->all()
			);
		}
	}

	private function getColumnDictsByRowIds(array $row_ids): array
	{
		$where = ['row_id' => $row_ids];

		return $this->db->sqlDicts(
			'SELECT id, row_id, `column` FROM platform_once_history_3_column WHERE ?',
			sqlWhere($where)
		);
	}

	public function getEmptyGtidBinlogCount(): int
	{
		$where = ['gtid' => ''];

		return intval($this->db->sqlCount('platform_once_history_3_binlog', $where));
	}

	/**
	 * @return int|null
	 */
	public function getRecentEmptyGtidBinlogId()
	{
		$where = ['gtid' => ''];

		return $this->db->sqlData(
			'SELECT id FROM platform_once_history_3_binlog WHERE ? ORDER BY id DESC ?',
			sqlWhere($where),
			sqlLimit(1)
		);
	}

	public function getEmptyGtidBinlogDictsByLesserEqualId(int $id, int $limit): array
	{
		$where = [
			'gtid' => '',
			'id' => sqlLesserEqual($id)
		];

		return $this->db->sqlDicts(
			'SELECT * FROM platform_once_history_3_binlog WHERE ? ORDER BY id DESC ?',
			sqlWhere($where),
			sqlLimit($limit)
		);
	}

	public function getEmptyGtidBinlogDictsByLesserId(int $id, int $limit): array
	{
		$where = [
			'gtid' => '',
			'id' => sqlLesser($id)
		];

		return $this->db->sqlDicts(
			'SELECT * FROM platform_once_history_3_binlog WHERE ? ORDER BY id DESC ?',
			sqlWhere($where),
			sqlLimit($limit)
		);
	}

	/**
	 * @param int $id
	 * @param int $offset
	 *
	 * @return int|null
	 */
	public function getEmptyGtidBinlogIdByLesserIdAndOffset(int $id, int $offset)
	{
		$where = [
			'gtid' => '',
			'id' => sqlLesser($id)
		];

		return $this->db->sqlData(
			'SELECT id FROM platform_once_history_3_binlog WHERE ? ORDER BY id DESC ?',
			sqlWhere($where),
			sqlLimit($offset, 1)
		);
	}

	public function updateBinlogGtid(int $id, string $gtid)
	{
		$update = ['gtid' => $gtid];
		$where = ['id' => $id];

		return $this->db->sqlUpdate('platform_once_history_3_binlog', $update, $where);
	}
}
