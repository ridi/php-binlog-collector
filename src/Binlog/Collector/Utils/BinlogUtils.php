<?php

namespace Binlog\Collector\Utils;

use Binlog\Collector\Exception\MsgException;

class BinlogUtils
{
    /**
     * ex: mariadb-bin.000015 -> mariadb-bin.000016
     *     mariadb-bin.999999 -> mariadb-bin.000000
     *
     * @param string $binlog_file_name
     *
     * @return string
     */
    public static function calculateNextSeqFile(string $binlog_file_name): string
    {
        $pos = strrpos($binlog_file_name, '.');
        if ($pos !== false) {
            $prefix_filename = substr($binlog_file_name, 0, $pos);
            $zero_pad_sequence = substr($binlog_file_name, $pos + 1);
            $next_sequence = intval($zero_pad_sequence) + 1;

            if (strlen(strval($next_sequence)) > strlen($zero_pad_sequence)) {
                $next_sequence = 0;
            }

            $new_suffix = str_pad($next_sequence, strlen($zero_pad_sequence), '0', STR_PAD_LEFT);

            return "{$prefix_filename}.{$new_suffix}";
        }

        return '';
    }

    public static function getSeqByBinlogFileName(string $binlog_file_name): int
    {
        $pos = strrpos($binlog_file_name, '.');
        if ($pos !== false) {
            $zero_pad_sequence = substr($binlog_file_name, $pos + 1);

            return intval($zero_pad_sequence);
        }
        throw new MsgException("invalid binlog_file_name: {$binlog_file_name}");
    }

    public static function calculatePreviousSeqFile(string $binlog_file_name): string
    {
        $pos = strrpos($binlog_file_name, '.');
        if ($pos !== false) {
            $prefix_filename = substr($binlog_file_name, 0, $pos);
            $zero_pad_sequence = substr($binlog_file_name, $pos + 1);
            $current_sequence = intval($zero_pad_sequence);

            if ($current_sequence === 0) {
                $new_suffix = str_pad('', strlen($zero_pad_sequence), '9', STR_PAD_LEFT);
            } else {
                $new_suffix = str_pad($current_sequence - 1, strlen($zero_pad_sequence), '0', STR_PAD_LEFT);
            }

            return "{$prefix_filename}.{$new_suffix}";
        }

        return '';
    }
}
