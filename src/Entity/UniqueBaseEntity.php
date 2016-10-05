<?php
namespace Eslider\Entity;

/**
 * Class BaseEntity
 *
 * @author  Mohamed Tahrioui <mohamed.tahrioui@wheregroup.com>
 */
class UniqueBaseEntity extends BaseEntity
{
    /* @var int ID */
    protected $id;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
}