<?php

namespace Binlog\Collector\Dto;

use Binlog\Collector\Utils\BinlogUtils;

/**
 * Class OnlyBinlogOffsetDto
 * @package Binlog\Collector\Dto
 */
class OnlyBinlogOffsetDto
{
	/** @var string */
	public $file_name;
	/** @var int */
	public $position;
	/** @var string */
	public $date;

	public static function importOnlyBinlogOffset(
		string $file_name = null,
		int $position = null,
		string $date = null
	): self {
		$dto = new self();
		$dto->file_name = $file_name;
		$dto->position = $position;
		$dto->date = $date;

		return $dto;
	}

	public function __toString(): string
	{
		if ($this->date === null) {
			return "[{$this->file_name}/{$this->position}]";
		} else {
			return "[{$this->date}/{$this->file_name}/{$this->position}]";
		}
	}

	public function getBinlogKey(): string
	{
		return $this->file_name . '|' . $this->position;
	}

	/**
	 * If the OnlyBinlogOffsetDto is equal to the argument then 0 is returned.
	 * If the OnlyBinlogOffsetDto is less than the argument then -1 is returned.
	 * If the OnlyBinlogOffsetDto is greater than the argument then 1 is returned.
	 *
	 * @param OnlyBinlogOffsetDto $targetDto
	 *
	 * @return int
	 */
	public function compareTo(OnlyBinlogOffsetDto $targetDto): int
	{
		$current_seq = BinlogUtils::getSeqByBinlogFileName($this->file_name);
		$target_seq = BinlogUtils::getSeqByBinlogFileName($targetDto->file_name);

		if ($current_seq < $target_seq) {
			return -1;
		} elseif ($current_seq > $target_seq) {
			return 1;
		}

		if ($this->position < $targetDto->position) {
			return -1;
		} elseif ($this->position > $targetDto->position) {
			return 1;
		}

		return 0;
	}
}
