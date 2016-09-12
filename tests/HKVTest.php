<?php
use Eslider\SpatialGeometry;
use Eslider\SpatialiteShellDriver;
use Eslider\Driver\HKVStorage;
use Eslider\Entity\HKV;

/**
 * Test HKV Storage component
 */
class ConfiguratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var HKVStorage */
    protected $storage;

    /** @var HKV */
    protected static $hkv;

    public function setUp()
    {
        $this->storage = new HKVStorage("hkv.db.sqlite");
        parent::setUp();
    }

    /**
     * Test creating data base file and table
     */
    public function testCreateDatabase()
    {
        $storage   = $this->storage;
        $db        = $storage->db();
        $tableName = $storage->getTableName();
        $tableInfo = $db->getTableInfo($tableName);
        $this->assertTrue(count($tableInfo) > 1);
    }

    /**
     * Test save configuration
     */
    public function testSaveConfiguration()
    {
        $testData = array(
            'parentId' => null,
            'key'      => 'application',
            'value'    => array(
                'obj'   => $this->storage,
                'roles' => array(
                    'read'  => array('test1', 'test2'),
                    'write' => array('test1', 'test2'),
                ),
            ),
            'children' => array(
                array(
                    'key'   => 'title',
                    'value' => 'Test'
                ),
                array(
                    'key'   => 'description',
                    'value' => 'Test application'
                ),
                array(
                    'key'   => 'component',
                    'value' => $this->storage
                ),
                array(
                    'key'      => 'elements',
                    'children' => array(
                        array(
                            'key'      => 'element',
                            'type'     => 'MapbenderCoreBundle/MapElement',
                            'children' => array(
                                array(
                                    'key'   => 'container',
                                    'value' => 'Content'
                                ),
                                array(
                                    'key'   => 'width',
                                    'value' => 'auto'
                                ),
                                array(
                                    'key'   => 'height',
                                    'value' => '200px'
                                )
                            )
                        ),
                        array(
                            'key'      => 'element',
                            'type'     => 'MapbenderCoreBundle/Digitizer',
                            'children' => array(
                                array(
                                    'key'   => 'container',
                                    'value' => 'Content'
                                ),
                                array(
                                    'key'   => 'width',
                                    'value' => 'auto'
                                ),
                                array(
                                    'key'   => 'height',
                                    'value' => '200px'
                                )
                            )
                        )
                    )
                )
            )
        );
        $storage  = $this->storage;
        $hkv      = new HKV($testData);
        $storage->save($hkv);
        self::$hkv = $hkv;
    }

    /**
     * Test retrieve HKV
     */
    public function testRestoreHKV()
    {
        $storage  = $this->storage;
        $hkv      = self::$hkv;
        $id       = $hkv->getId();
        $restored = $storage->getById($id);
        $this->assertEquals($id, $restored->getId());
        $this->assertEquals(count($restored->getChildren()), count($hkv->getChildren()));
    }

    public function testSaveArray()
    {
        $storage       = $this->storage;
        $testKey       = 'testSaveArray';
        $testArray     = array('test'         => 'xxx',
                               'configuRATor' => $storage,
                               'someThing'    => array(
                                   'roles' => array(
                                       'xxx',
                                       'ddd'
                                   )
                               )
        );
        $hkv           = $storage->saveData($testKey, $testArray);
        $restoredArray = $storage->getData($testKey);
        $this->assertEquals($testArray, $restoredArray);
        $this->assertTrue($hkv->hasId());
    }

    public function testGeo()
    {
        $storage   = $this->storage;
        $db        = $storage->db();
        $gdb       = new SpatialiteShellDriver($db->getFilePath());
        $tableName = "geo-test";

        if (!$gdb->hasTable($tableName)) {
            $gdb->initDbFile();
            $gdb->createTable($tableName);
            $gdb->addColumn($tableName, "title");
            $gdb->addGeometryColumn($tableName, 'geom', 4326, 'POINT');
        }
        $id = $gdb->insert($tableName, array(
            'title' => "xxx",
            'geom'  => new SpatialGeometry('POINT(1.2345 2.3456)', SpatialGeometry::TYPE_WKT, 4326)
        ));
        var_dump($id);
    }
}
