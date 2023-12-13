<?php

namespace Somms\BV2Observation\Source\Database;

use PDO;

class DatabaseCollection implements \Iterator
{
    private $statement;
    private $position;
    private $current;

    public function __construct($statement)
    {
        $this->statement = $statement;
        $this->position = 0;
        $this->current = null;
    }

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        $this->position++;
        $this->current = $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        // Utiliza el índice de la fila como clave
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return $this->current !== false;
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->position = 0;
        $this->statement->execute();
        $this->current = $this->statement->fetch(PDO::FETCH_ASSOC);
    }
}