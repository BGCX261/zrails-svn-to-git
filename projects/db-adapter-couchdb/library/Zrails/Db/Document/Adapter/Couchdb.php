<?php

/**
 ** @see Zend_Json
 */
require_once 'Zend/Json.php';

/**
 ** @see Zrails_Rest_Client
 */
require_once 'Zrails/Rest/Client.php';

/**
 ** @see Zrails_Db_Document_Adapter_Abstract
 */
require_once 'Zrails/Db/Document/Adapter/Abstract.php';



/**
 * Class for connecting to Couchdb databases and performing common operations.
 *
 * @category   Zrails
 * @package    Zrails_Db
 * @subpackage Adapter
 * @author     necromant2005@gmail.com
 * @copyright  necromant2005 (http://necromant2005.blogspot.com/)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zrails_Db_Document_Adapter_Couchdb extends Zrails_Db_Document_Adapter_Abstract
{
    /**#@+
     * Field name constants
     */
    const FIELD_ID        = "_id";
    const FIELD_REV       = "_rev";
    const FIELD_REVISIONS = "_revisions";
    const FIELD_IDS       = "ids";
    const FIELD_ROWS      = "rows";
    /**#@-*/

    /**#@+
     * Error name constants
     */
    const ERROR_NOT_FOUND          = 1;
    const ERROR_BAD_MATCH          = 2;
    const ERROR_FILE_EXISTS        = 3;
    const ERROR_INCORECT_MIME_TYPE = 4;
    /**#@-*/

    /**#@+
     * Error name constants
     */
    const DB_MAP       = "map";
    const DB_VIEWS     = "views";
    const DB_TEMP_VIEW = "_temp_view";
    const DB_DEGIGN    = "_design";
    /**#@-*/

    /**
     * Error description
     * @var array
     */
    protected $_errors = array(
        "not_found"           => self::ERROR_NOT_FOUND,
        "badmatch"            => self::ERROR_BAD_MATCH,
        "file_exists"         => self::ERROR_FILE_EXISTS,
        "incorrect_mime_type" => self::ERROR_INCORECT_MIME_TYPE,
    );

    /**
     * Decode request response
     *
     * @param Zend_Http_Response $response response of request
     * @throws Zend_Db_Exception
     * @return array
     */
    protected function _decodeResponse(Zend_Http_Response $response)
    {
        $result = Zend_Json::decode($response->getBody());
        if (is_null($result)) {
            throw new Zend_Db_Exception($response->getMessage());
        }

        if ($result["error"]) {
            throw new Exception($result["reason"], $this->_errors[$result["error"]]);
        }
        return $result;
    }

    /**
     * Get document by id
     *
     * @param string $id unique document identificator
     * @param string @rev revision number
     * @return array
     */
    public function getDocument($id, $rev="")
    {
        if (!$id) throw new Exception("Empty id for getting document");
        $this->_connect();
        if (empty($rev)) return $this->_decodeResponse($this->_connection->restGet($id));
        return $this->_decodeResponse($this->_connection->restGet("$id?rev=$rev"));
    }

    /**
     * Post document with autogenerated id
     *
     * @param array @data data for post
     * @return array
     */
    public function postDocument(array $data = array())
    {
        if (empty($data)) throw new Core_Db_Adapter_Document_Exception("Empty data for method post");
        $this->_connect();
        return $this->_decodeResponse($this->_connection->restPost("", Zend_Json::encode($data)));
    }

    /**
     * Put document with id
     *
     * @param string $id unique document identificator
     * @param array @data for put
     * @return array
     */
    public function putDocument($id, array $data = array())
    {
        if (is_array($id)) {
            $data = $id;
            $id = $data[self::FIELD_ID];
        }
        if (empty($id)) throw new Core_Db_Adapter_Document_Exception("Empty id for method put");
        $this->_connect();
        return $this->_decodeResponse($this->_connection->restPut($id, Zend_Json::encode($data)));
    }

    /**
     * Delete document with id
     *
     * @param string $id unique document identificator
     * @param string @rev revision number
     * @param array  @data for put
     * @return array
     */
    public function deleteDocument($id, $rev="", array $data=array())
    {
        if (is_array($id)) {
            $data = $id;
        } elseif ($id && empty($rev)) {
            $data = $this->getDocument($id);
        }

        $id  = $data[self::FIELD_ID];
        $rev = $data[self::FIELD_REV];

        if (empty($id)) throw new Core_Db_Adapter_Document_Exception("Empty id for method delete");
        if (empty($rev)) throw new Core_Db_Adapter_Document_Exception("Empty rev for method delete");

        $this->_connect();
        return $this->_decodeResponse($this->_connection->restDelete("$id?rev=$rev"));
    }

    /**
     * Get all revision of Document
     *
     * @param string $id unique document identificator
     * @return array
     */
    public function getDocumentRevisions($id)
    {
        if (is_array($id)) {
            $id = $id[self::FIELD_ID];
        }
        $result = $this->_decodeResponse($this->_connection->restGet("$id?revs=true"));
        return $result[self::FIELD_REVISIONS][self::FIELD_IDS];
    }

    /**
     * Get all documents in the path
     *
     * @param string @path path of document (backet)
     * @param bool  @only_rows (Optional) return only rows without revision information
     * @return array
     */
    public function getDocuments($path, $only_rows=true)
    {
        $this->_connect();
        $result = $this->_decodeResponse($this->_connection->restGet($path));
        if ($only_rows) return $result[self::FIELD_ROWS];
        return $result;
    }

    /**
     * Get all documents in the path with limits
     *
     * @param string @startkey (Optional) start key
     * @param string @endkey (Optional) end key
     * @param int    @limit (Optional) limit documents in result set
     * @param bool   @descending (Optional)order by descending
     * @param bool   @only_rows (Optional) return only rows without revision information
     * @return array
     */
    public function getAllDocuments($startkey=null, $endkey=null, $limit=null, $descending=false, $only_rows=true)
    {
        $params = array();
        if ($startkey) $params[] = "startkey=$startkey";
        if ($endkey) $params[] = "endkey=$startkey";
        if ($limit) $params[] = "limit=$limit";
        if ($descending) $params[] = "descending=true";
        return $this->getDocuments("_all_docs?".join("&", $params), $only_rows);
    }

    /**
     * Get all documents in the path with limits
     *
     * @param string @startkey (Optional) start key
     * @param string @endkey (Optional) end key
     * @param int    @limit (Optional) limit documents in result set
     * @param bool   @descending (Optional)order by descending
     * @param bool   @only_rows (Optional) return only rows without revision information
     * @return array
     */
    public function getAllDocumentsBySeq($startkey=null, $endkey=null, $limit=null, $descending=false, $only_rows=true)
    {
        $params = array();
        if ($startkey) $params[] = "startkey=$startkey";
        if ($endkey) $params[] = "endkey=$startkey";
        if ($limit) $params[] = "limit=$limit";
        if ($descending) $params[] = "descending=true";
        return $this->getDocuments("_all_docs_by_seq?".join("&", $params), $only_rows);
    }

    /**
     * Init the connection.
     *
     * @return void
     */
    protected function _connect()
    {
        $dns = "http://";
        $dns.= $this->_config['host'];
        $dns.= ":";
        $dns.= ($this->_config['port']) ? $this->_config['port'] : 5984;
        $dns.= '/' . $this->_config['dbname'] . '/';
        $this->_connection = new Zrails_Rest_Client($dns);
    }

    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function closeConnection()
    {
        $this->_connection = null;
    }

    /**
     * Test connection
     *
     * @return bool
     */
    public function isConnected()
    {
        return is_object($this->_connection);
    }

    /**
     *Return current connection
     *
     * @return mixed
     */
    public function getConnection()
    {
        $this->_connect();
        return $this->_connection;
    }

    /**
     * Get current database server version
     *
     * @return string
     */
    public function getServerVersion()
    {
        $this->_connect();
        $result = $this->_decodeResponse($this->_connection->restGet("/"));
        return $result["version"];
    }

    /**
     * Create database
     *
     * @param string $name name of database
     * @throws Core_Db_Adapter_Document_Exception
     * @return bool
     */
    public function createDatabase($name)
    {
        if (empty($name)) throw new Core_Db_Adapter_Document_Exception("Empty database name for drop");
        $this->_connect();
        return $this->_decodeResponse($this->_connection->restPut("/$name/"));
    }

    /**
     * Drop database
     *
     * @param string $name name of database
     * @throws Core_Db_Adapter_Document_Exception
     * @return bool
     */
    public function dropDatabase($name)
    {
        if (empty($name)) throw new Core_Db_Adapter_Document_Exception("Empty database name for drop");
        $this->_connect();
        return $this->_decodeResponse($this->_connection->restDelete("/$name/"));
    }

    /**
     * Prepares and executes an SQL statement with bound data.
     *
     * @param  mixed  $sql  The SQL statement with placeholders.
     *                      May be a string or Zend_Db_Select.
     * @return Zend_Db_Statement_Interface
     */
    public function query($query)
    {
        if (!is_array($query)) {
           $query = array(self::DB_MAP=>$query);
        }
        $response = $this->_connection->restPost(self::DB_TEMP_VIEW, Zend_Json::encode($query));
        $result = $this->_decodeResponse($response);
        return $result[self::FIELD_ROWS];
    }

    /**
     * Create view for current query in namespace
     *
     * @param  string $namespace_name name of namespace
     * @param  mixed  $sql  The SQL statement with placeholders.
     *                      May be a string or Zend_Db_Select.
     * @return Zend_Db_Statement_Interface
     */
    public function createView($namespace, $name, $query)
    {
        if (!is_array($query)) {
           $query = array(self::DB_MAP=>$query);
        }
        $doc = array();
        $doc[self::DB_VIEWS] = array();
        try {
            $doc = $this->getDocument(self::DB_DEGIGN . "/" . $namespace);
        } catch (Exception $E) {}
        $doc[self::DB_VIEWS][$name] = $query;
        return $this->putDocument(self::DB_DEGIGN . "/" . $namespace, $doc);
    }

   /**
     * Executes view
     *
     * @param  string $namespace_name name of namespace
     * @return Zend_Db_Statement_Interface
     */
    public function queryView($namespace, $name)
    {
        return $this->getDocuments(self::DB_DEGIGN . "/$namespace/_view/$name");
    }

}

