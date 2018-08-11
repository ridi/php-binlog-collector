<?php

namespace Binlog\Collector\Library;

class FileLock
{
    /** @var string */
    private $lock_file_name;

    private $lock_file;

    /** @var bool */
    private $is_has_lock = false;

    public function __construct(string $name)
    {
        $filtered_name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        $this->lock_file_name = $filtered_name . '.lock';
    }

    /**
     * lock 얻고 실패하면 throw
     * @throws \RuntimeException
     */
    public function lock(): void
    {
        $this->tryLock();

        if (!$this->isHasLock()) {
            throw new \RuntimeException($this->lock_file_name . ' already exists, exiting');
        }
    }

    /**
     * lock 얻기 시도하고 성공하면 true, 실패하면 false
     * @return bool
     */
    public function tryLock(): bool
    {
        $this->lock_file = fopen(sys_get_temp_dir() . '/' . $this->lock_file_name, 'w+');
        $this->is_has_lock = flock($this->lock_file, LOCK_EX | LOCK_NB);

        return $this->is_has_lock;
    }

    public function unlock(): void
    {
        if ($this->isHasLock()) {
            flock($this->lock_file, LOCK_UN);
            @fclose($this->lock_file);

            $this->is_has_lock = false;
        }
    }

    /**
     * @return bool 현재 lock을 가지고 있는지 여부
     */
    public function isHasLock(): bool
    {
        return $this->is_has_lock;
    }
}
