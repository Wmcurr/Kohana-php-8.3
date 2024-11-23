<?php
declare(strict_types=1);

class Kohana_Database_Result_Cached extends Database_Result implements SeekableIterator
{
    public function __construct(array $result, $sql, $as_object = null)
    {
        parent::__construct($result, $sql, $as_object);
        $this->_total_rows = count($result);
    }

    public function __destruct()
    {
        // Cached results do not use resources
    }

    public function cached(): Database_Result_Cached
    {
        return $this;
    }

    public function seek(int $offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->_current_row = $offset;
        } else {
            throw new OutOfBoundsException("Invalid seek position ($offset)");
        }
    }

    public function current(): mixed
    {
        return $this->valid() ? $this->_result[$this->_current_row] : null;
    }
}
