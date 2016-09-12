<?php

namespace Eslider\Driver;

use Eslider\Entity\HKV;
use Eslider\Entity\HKVSearchFilter;
use Zumba\Util\JsonSerializer;

/**
 * Class HKVStorage
 *
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class HKVStorage
{
    const NULL_BYTE = "\1NULL\1";

    /** @var string ID field name */
    const ID_FIELD = 'id';

    /** @var string Parent id field name */
    const PARENT_ID_FIELD = 'parentId';

    /** @var string HKV table name */
    protected $tableName;

    /** @var SqliteExtended SQLite driver connection */
    protected $db;

    /**
     * HKVStorage constructor.
     *
     * @param string $path
     * @param string $tableName
     */
    public function __construct(
        $path = "hkv-storage.db.sqlite",
        $tableName = "key_values")
    {
        $emptyDatabase   = !file_exists($path);
        $this->db        = new SqliteExtended($path);
        $this->tableName = $tableName;

        if ($emptyDatabase) {
            $this->createDbStructure();
        }
    }

    /**
     * Get database connection handler
     *
     * @return SqliteExtended db Database connection handler
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Get HKV by id
     *
     * @param int    $id            HKV id
     * @param bool   $fetchChildren Get children flag
     * @param string $scope
     * @return HKV
     */
    public function getById($id, $fetchChildren = true, $scope = null)
    {
        $filter = new HKVSearchFilter();
        $filter->setId($id);
        $filter->setScope($scope);

        if ($fetchChildren) {
            $filter->setFetchMethod(HKVSearchFilter::FETCH_ONE_AND_CHILDREN);
        } else {
            $filter->setFetchMethod(HKVSearchFilter::FETCH_ONE_WITHOUT_CHILDREN);
        }

        return $this->get($filter);
    }

    /**
     * Get by HKV filter
     *
     * @param HKV|HKVSearchFilter $filter
     * @return HKV
     */
    public function get(HKVSearchFilter $filter)
    {
        $db       = $this->db();
        $query    = $this->createQuery($filter);
        $dataItem = new HKV($db->fetchRow($query));

        if ($dataItem->isArray() || $dataItem->isObject()) {
            $dataItem->setValue(static::decodeValue($dataItem->getValue()));
        }

        if ($filter->shouldFetchChildren()) {
            $children = $this->getChildren($dataItem->getId(), true, $filter->getScope());
            $dataItem->setChildren($children);
        }
        return $dataItem;
    }

    /**
     * Get children
     *
     * @param      $id int
     * @param bool $fetchChildren
     * @param null $scope
     * @return HKV[]
     */
    public function getChildren($id, $fetchChildren = true, $scope = null)
    {
        $db       = $this->db();
        $children = array();
        $filter   = new HKVSearchFilter();

        $filter->setParentId($id);
        $filter->setFields(array(self::ID_FIELD));

        foreach ($db->queryAndFetch($this->createQuery($filter)) as $row) {
            $children[] = $this->getById($row[ self::ID_FIELD ], $fetchChildren, $scope);
        }

        return $children;
    }


    /**
     * Create database4 file
     */
    protected function createDbStructure()
    {
        $db        = $this->db();
        $tableName = $this->tableName;
        if (!$db->hasTable($tableName)) {
            $db->createTable($tableName);
            $fieldNames = $this->getFieldNames();
            foreach ($fieldNames as $fieldName) {
                $db->addColumn($tableName, $fieldName);
            }
        }
    }

    /**
     * Remove database file
     */
    public function destroy()
    {
        return $this->db()->destroy();
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Save HKV
     *
     * @param HKV  $dataItem
     * @param null $time
     * @return HKV
     */
    public function save(HKV $dataItem = null, $time = null)
    {
        $db        = $this->db();
        $tableName = $this->getTableName();
        $data      = $dataItem->toArray();

        if (!$time) {
            $time = time();
        }

        $data["creationDate"] = $time;

        if ($dataItem->isArray() || $dataItem->isObject()) {
            $data["value"] = static::encodeValue($data["value"]);
        }

        unset($data["children"]);

        if ($dataItem->hasId()) {
            $db->update($tableName, $data, $dataItem->getId());
        } else {
            try {
                $id = $db->insert($tableName, $data);
                $dataItem->setId($id);
            } catch (\Exception $e) {
                var_dump($e);
            }

        }

        if ($dataItem->hasChildren()) {
            foreach ($dataItem->getChildren() as $child) {
                $child->setParentId($dataItem->getId());
                $this->save($child, $time);
            }
        }

        return $dataItem;
    }

    /**
     * @param string      $key
     * @param null|string $scope
     * @param null|int    $parentId
     * @param null|int    $userId
     * @return HKV
     */
    public function saveData($key, $value, $scope = null, $parentId = null, $userId = null)
    {
        $dataItem = new HKV();
        $isArray  = is_array($value);
        $dataItem->setKey($key);
        $dataItem->setParentId($parentId);
        $dataItem->setScope($scope);
        $type = gettype($value);
        $dataItem->setType($type);
        $dataItem->setUserId($userId);

        if (!$isArray) {
            $dataItem->setValue($value);
        }
        if ($type == "object") {
            $dataItem->setType(get_class($value));
        }

        $this->save($dataItem);

        if ($isArray) {
            $childParentId = $dataItem->getId();
            $children      = array();
            foreach ($value as $subKey => $item) {
                $children[] = $this->saveData($subKey, $item, $scope, $childParentId, $userId);
            }
            $dataItem->setChildren($children);
        }

        return $dataItem;
    }

    /**
     * Get field names
     *
     * @return array
     */
    protected function getFieldNames()
    {
        return array(
            static::PARENT_ID_FIELD,
            'key',
            'type',
            'value',
            'scope',
            'creationDate',
            'userId'
        );
    }

    /**
     * Get as data
     *
     * @param string      $key
     * @param null|string $scope
     * @param null|int    $parentId
     * @param null|int    $userId
     * @return HKV
     */
    public function getData($key, $scope = null, $parentId = null, $userId = null)
    {
        $filter = new HKVSearchFilter();
        $filter->setKey($key);
        $filter->setParentId($parentId);
        $filter->setScope($scope);
        $filter->setUserId($userId);
        $hkv  = $this->get($filter);
        $data = static::denormalize($hkv);

        return $data;
    }

    /**
     * Denormalize object to array
     *
     * @param HKV $hkv
     * @return null|array|mixed
     */
    public static function denormalize(HKV $hkv)
    {
        $result = null;
        if ($hkv->getType() == 'array') {
            $result = array();
            foreach ($hkv->getChildren() as $child) {
                if ($child->getType() == 'array') {
                    $result[ $child->getKey() ] = static::denormalize($child);
                } else {
                    $result[ $child->getKey() ] = $child->getValue();
                }
            }
        } else {
            $result = $hkv->getValue();
        }
        return $result;
    }

    /**
     * Encode value
     *
     * @param $value
     * @return string
     */
    public static function encodeValue($value)
    {
        $serializer = new JsonSerializer();
        return $serializer->serialize($value);
        //return str_replace("\0", self::NULL_BYTE, serialize($value));
    }

    /**
     * Decode value
     *
     * @param $value
     * @return mixed
     */
    public static function decodeValue($value)
    {
        $serializer = new JsonSerializer();
        return $serializer->unserialize($value);
        //return unserialize(str_replace(self::NULL_BYTE, "\0", $value));
    }

    /**
     * Create SQL query
     *
     * @param HKVSearchFilter $filter
     * @return string SQL
     */
    public function createQuery(HKVSearchFilter $filter)
    {
        $db     = $this->db();
        $sql    = array();
        $where  = array();
        $fields = $filter->getFields();
        $sql[]  = 'SELECT ' . ($fields ? implode(',', $fields) : '*');
        $sql[]  = 'FROM ' . $db->quote($this->tableName);

        $quotedKeyName      = (string)$db->quote('key');
        $quotedCreationDate = (string)$db->quote('creationDate');

        if ($filter->hasId()) {
            $where[] = static::ID_FIELD . '=' . intval($filter->getId());
        } elseif ($filter->getKey()) {
            $where[] = $quotedKeyName . ' LIKE ' . $db::escapeValue($filter->getKey());
        }

        if ($filter->getParentId()) {
            $where[] = static::PARENT_ID_FIELD . '=' . intval($filter->getParentId());
        }

        if ($filter->getScope()) {
            $where[] = $db->quote('scope') . ' LIKE ' . $db::escapeValue($filter->getScope());
        } else {
            $where[] = $db->quote('scope') . ' IS NULL';
        }

        if ($filter->getType()) {
            $where[] = $quotedKeyName . ' LIKE ' . $db::escapeValue($filter->getType());
        }

        $sql[] = 'WHERE ' . implode(' AND ', $where);
        $sql[] = 'GROUP BY ' . $quotedKeyName;
        $sql[] = 'ORDER BY ' . $quotedCreationDate . ' DESC';

        if ($filter->hasLimit()) {
            $sql[] = 'LIMIT ' . $filter->getFetchLimit();
        }

        return implode(' ', $sql);
    }
}