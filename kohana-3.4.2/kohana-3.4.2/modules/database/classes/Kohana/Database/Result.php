<?php

/**
 * Обертка для результата базы данных. См. [Результаты](/database/results) для использования и примеров.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 * @autor      Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    https://kohana.top/license
 */
abstract class Kohana_Database_Result implements Countable, Iterator, SeekableIterator, ArrayAccess
{
    // Выполненный SQL для этого результата
    protected $_query;
    // Сырой ресурс результата
    protected $_result;
    // Общее количество строк и текущая строка
    protected $_total_rows = 0;
    protected $_current_row = 0;
    // Возвращать строки как объект или ассоциативный массив
    protected $_as_object;
    // Параметры для __construct при использовании результатов объектов
    protected $_object_params = null;

    /**
     * Устанавливает общее количество строк и сохраняет результат локально.
     *
     * @param   mixed   $result     результат запроса
     * @param   string  $sql        SQL запрос
     * @param   mixed   $as_object
     * @param   array   $params
     * @return  void
     */
    public function __construct($result, $sql, $as_object = false, array $params = null)
    {
        // Сохраняем результат локально
        $this->_result = $result;

        // Сохраняем SQL локально
        $this->_query = $sql;

        if (is_object($as_object)) {
            // Получаем имя класса объекта
            $as_object = get_class($as_object);
        }

        // Результаты как объекты или ассоциативные массивы
        $this->_as_object = $as_object;

        if ($params) {
            // Параметры конструктора объекта
            $this->_object_params = $params;
        }
    }

    /**
     * Уничтожение результата очищает все открытые наборы результатов.
     *
     * @return  void
     */
    abstract public function __destruct();

    /**
     * Получить кэшированный результат базы данных из текущего итератора результата.
     *
     *     $cachable = serialize($result->cached());
     *
     * @return  Database_Result_Cached
     * @since   3.0.5
     */
    public function cached()
    {
        return new Database_Result_Cached($this->as_array(), $this->_query, $this->_as_object);
    }

    /**
     * Возвращает все строки результата в виде массива.
     *
     *     // Индексированный массив всех строк
     *     $rows = $result->as_array();
     *
     *     // Ассоциативный массив строк по "id"
     *     $rows = $result->as_array('id');
     *
     *     // Ассоциативный массив строк, "id" => "name"
     *     $rows = $result->as_array('id', 'name');
     *
     * @param   string  $key    колонка для ассоциативных ключей
     * @param   string  $value  колонка для значений
     * @return  array
     */
    public function as_array($key = null, $value = null)
    {
        $results = [];

        if ($key === null AND $value === null) {
            // Индексированные строки

            foreach ($this as $row) {
                $results[] = $row;
            }
        } elseif ($key === null) {
            // Индексированные колонки

            if ($this->_as_object) {
                foreach ($this as $row) {
                    $results[] = $row->$value;
                }
            } else {
                foreach ($this as $row) {
                    $results[] = $row[$value];
                }
            }
        } elseif ($value === null) {
            // Ассоциативные строки

            if ($this->_as_object) {
                foreach ($this as $row) {
                    $results[$row->$key] = $row;
                }
            } else {
                foreach ($this as $row) {
                    $results[$row[$key]] = $row;
                }
            }
        } else {
            // Ассоциативные колонки

            if ($this->_as_object) {
                foreach ($this as $row) {
                    $results[$row->$key] = $row->$value;
                }
            } else {
                foreach ($this as $row) {
                    $results[$row[$key]] = $row[$value];
                }
            }
        }

        $this->rewind();

        return $results;
    }

    /**
     * Возвращает названную колонку из текущей строки.
     *
     *     // Получить значение "id"
     *     $id = $result->get('id');
     *
     * @param   string  $name     колонка для получения
     * @param   mixed   $default  значение по умолчанию, если колонка не существует
     * @return  mixed
     */
    public function get($name, $default = null)
    {
        $row = $this->current();

        if ($this->_as_object) {
            if (isset($row->$name))
                return $row->$name;
        }
        else {
            if (isset($row[$name]))
                return $row[$name];
        }

        return $default;
    }

    /**
     * Реализует [Countable::count], возвращает общее количество строк.
     *
     *     echo count($result);
     *
     * @return  integer
     */
    public function count(): int
    {
        return $this->_total_rows;
    }

    /**
     * Реализует [ArrayAccess::offsetExists], определяет, существует ли строка.
     *
     *     if (isset($result[10]))
     *     {
     *         // Строка 10 существует
     *     }
     *
     * @param   int     $offset
     * @return  boolean
     */
    public function offsetExists($offset): bool
    {
        return ($offset >= 0 AND $offset < $this->_total_rows);
    }

    /**
     * Реализует [ArrayAccess::offsetGet], получает заданную строку.
     *
     *     $row = $result[10];
     *
     * @param   int     $offset
     * @return  mixed
     */
    public function offsetGet($offset): mixed
    {
        if (!$this->seek($offset))
            return null;

        return $this->current();
    }

    /**
     * Реализует [ArrayAccess::offsetSet], выбрасывает ошибку.
     *
     * [!!] Вы не можете изменять результат базы данных.
     *
     * @param   int     $offset
     * @param   mixed   $value
     * @return  void
     * @throws  Kohana_Exception
     */
    final public function offsetSet($offset, $value): void
    {
        throw new Kohana_Exception('Результаты базы данных только для чтения');
    }

    /**
     * Реализует [ArrayAccess::offsetUnset], выбрасывает ошибку.
     *
     * [!!] Вы не можете изменять результат базы данных.
     *
     * @param   int     $offset
     * @return  void
     * @throws  Kohana_Exception
     */
    final public function offsetUnset($offset): void
    {
        throw new Kohana_Exception('Результаты базы данных только для чтения');
    }

    /**
     * Реализует [Iterator::key], возвращает текущий номер строки.
     *
     *     echo key($result);
     *
     * @return  integer
     */
    public function key(): int
    {
        return $this->_current_row;
    }

    /**
     * Реализует [Iterator::next], переходит к следующей строке.
     *
     *     next($result);
     *
     * @return  void
     */
    public function next(): void
    {
        ++$this->_current_row;
    }

    /**
     * Реализует [Iterator::prev], переходит к предыдущей строке.
     *
     *     prev($result);
     *
     * @return  $this
     */
    public function prev()
    {
        --$this->_current_row;
        return $this;
    }

    /**
     * Реализует [Iterator::rewind], устанавливает текущую строку в ноль.
     *
     *     rewind($result);
     *
     * @return  void
     */
    public function rewind(): void
    {
        $this->_current_row = 0;
    }

    /**
     * Реализует [Iterator::valid], проверяет, существует ли текущая строка.
     *
     * [!!] Этот метод используется только внутренне.
     *
     * @return  boolean
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->_current_row);
    }

}
