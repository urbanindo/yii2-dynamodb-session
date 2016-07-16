<?php
/**
 * Session class file.
 * @author Petra Barus <petra.barus@gmail.com>
 */
namespace UrbanIndo\Yii2\DynamoDbSession;

use Yii;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

/**
 * This is a minimum class for handling session using AWS DynamoDB without
 * installing Our Yii2 DynamoDB.
 * 
 * @author Petra Barus <petra.barus@gmail.com>
 * @link https://github.com/urbanindo/yii2-dynamodb
 */
class Session extends \yii\web\Session {
    
    /**
     * Configuration for DynamoDB client.
     * @var array
     */
    public $config;

    /**
     * The name of the table.
     * @var string
     */
    public $tableName;

    /**
     * The column name where the data is stored.
     * @var string 
     */
    public $dataColumn = 'Data';

    /**
     * The column where the session ID is stored.
     * @var string
     */
    public $idColumn = 'ID';

    /**
     * The prefix for the key of the value stored in the session.
     * If not set this will use the application ID.
     * @var string
     */
    public $keyPrefix;
    
    /**
     * @var DynamoDbClient
     */
    private $_client;
    
    /**
     * Initialize the client.
     */
    public function init() {
        $this->_client = DynamoDbClient::factory($this->config);
        if ($this->keyPrefix === null) {
            $this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
        }
        parent::init();
    }
    
    /**
     * @return DynamoDbClient
     */
    public function getClient() {
        return $this->_client;
    }
    
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return boolean whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }
    
    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $marshaler = new Marshaler();
        $keys = [];
        $keys[$this->idColumn] = $id;
        try {
            $result = $this->getClient()->getItem([
                'TableName' => $this->tableName,
                'Key' => $marshaler->marshalItem($keys),
            ]);
            if (!isset($result['Item'])) {
                return '';
            }
            $values = $marshaler->unmarshalItem($result['Item']);
            if (!isset($values[$this->dataColumn])) {
                return '';
            }
            return $values[$this->dataColumn];
        } catch (\Exception $ex) {
            Yii::error(__CLASS__ . '::' . __METHOD__ . ': ' . $ex->getMessage(), 'yii2dynamodbsession');
            return false;
        }
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return boolean whether session write is successful
     */
    public function writeSession($id, $data)
    {
        $marshaler = new Marshaler();
        $values = [];
        $values[$this->idColumn] = $id;
        if (!empty($data)) {
            $values[$this->dataColumn] = $data;
        }
        try {
            $this->getClient()->putItem([
                'TableName' => $this->tableName,
                'Item' => $marshaler->marshalItem($values),
            ]);
            return true;
        } catch (\Exception $ex) {
            Yii::error(__CLASS__ . '::' . __METHOD__ . ': ' . $ex->getMessage(), 'yii2dynamodbsession');
            return false;
        }
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return boolean whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $marshaler = new Marshaler();
        $keys = [];
        $keys[$this->idColumn] = $id;
        try {
            $this->getClient()->deleteItem([
                'TableName' => $this->tableName,
                'Key' => $marshaler->marshalItem($keys),
            ]);
            return true;
        } catch (\Exception $ex) {
            Yii::error(__CLASS__ . '::' . __METHOD__ . ': ' . $ex->getMessage(), 'yii2dynamodbsession');
            return false;
        }
    }

    /**
     * Generates a unique key used for storing session data in cache.
     * @param string $id session variable name
     * @return string a safe cache key associated with the session variable name
     */
    protected function calculateKey($id)
    {
        return $this->keyPrefix . $id;
    }	
}
