<?php
namespace Eslider\Entity;

/**
 * Class HKVSearchFilter
 *
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class HKVSearchFilter extends HKV
{
    const FETCH_ONE_WITHOUT_CHILDREN  = 0;
    const FETCH_ONE_AND_CHILDREN      = 1;
    const FETCH_MANY_WITHOUT_CHILDREN = 2;
    const FETCH_MANY_AND_CHILDREN     = 3;

    /** @var array */
    protected $fields;
    protected $fetchMethod = self::FETCH_ONE_AND_CHILDREN;

    /** @var int limit fetching results */
    protected $fetchLimit;

    /**
     * @param mixed $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param int $fetchMethod
     * @return HKVSearchFilter
     */
    public function setFetchMethod($fetchMethod)
    {
        $this->fetchMethod = $fetchMethod;
        return $this;
    }

    /**
     * @return int
     */
    public function getFetchMethod()
    {
        return $this->fetchMethod;
    }

    /**
     * @return bool
     */
    public function shouldFetchChildren()
    {
        return $this->fetchMethod === static::FETCH_MANY_AND_CHILDREN
        || $this->fetchMethod === static::FETCH_ONE_AND_CHILDREN;
    }

    /**
     * @return int|null
     */
    public function getFetchLimit()
    {
        return $this->fetchLimit;
    }

    /**
     * @return bool
     */
    public function hasLimit()
    {
        return $this->fetchLimit !== null;
    }

    /**
     * @param int|null $fetchLimit
     */
    public function setFetchLimit($fetchLimit)
    {
        $this->fetchLimit = $fetchLimit;
    }
}