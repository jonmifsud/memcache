<?php

require_once FACE . '/interface.namespacedcache.php';

 /**
  * The CacheDatabase interface allows extensions to store data in Symphony's
  * database table, `tbl_cache`. At the moment, it is mostly unused by the core,
  * with the exception of the deprecated Dynamic XML datasource.
  *
  * This cache will be initialised by default if no other caches are specified
  * in the install.
  *
  * @see ExtensionManager#getCacheProvider()
  */
class Memcache implements iNamespacedCache
{
    /**
     * An instance of the memcached class used for caching
     *
     * @var MySQL
     */
    private $Memcached; //static?

    /**
     * The constructor for the Cacheable takes an instance of the
     * MySQL class and assigns it to `$this->Database`
     *
     * @param MySQL $Database
     *  An instance of the MySQL class to store the cached
     *  data in.
     */
    public function __construct()
    {
        // $this->Database = $Memcached;
        $this->Memcached = new Memcached();

        $serverDetails = Symphony::Configuration()->get('server_details', 'memcache');
        if (strlen(trim($serverDetails)) > 0) {
            $serverDetails = json_decode($serverDetails);
        } else {
            $serverDetails = array("127.0.0.1:11211");
        }

        // var_dump($serverDetails);die;

        foreach ($serverDetails as $key => $value) {
            $details = explode(':', $value);
            $this->Memcached->addServer($details[0],$details[1]);
        }
    }

    /**
     * Returns the human readable name of this cache type. This is
     * displayed in the system preferences cache options.
     *
     * @return string
     */
    public static function getName()
    {
        return 'Memcache';
    }

    /**
     * This function returns all the settings of the current Cache
     * instance.
     *
     * @return array
     *  An associative array of settings for this cache where the
     *  key is `getClass` and the value is an associative array of settings,
     *  key being the setting name, value being, the value
     */
    public function settings()
    {

    }

    private function getVersionedNamespace($namespace){
        $versionedNamespace = $this->Memcached->get($namespace);
        if (!$versionedNamespace){
            $versionedNamespace = $namespace . time();
            $this->Memcached->set($namespace,$versionedNamespace);
        }
        return $versionedNamespace;
    }

    /**
     * Given the hash of a some data, check to see whether it exists in
     * `tbl_cache`. If no cached object is found, this function will return
     * false, otherwise the cached object will be returned as an array.
     *
     * @param string $hash
     *  The hash of the Cached object, as defined by the user
     * @param string $namespace
     *  The namespace allows a group of data to be retrieved at once
     * @return array|boolean
     *  An associative array of the cached object including the creation time,
     *  expiry time, the hash and the data. If the object is not found, false will
     *  be returned.
     */
    public function read($hash, $namespace = null)
    {
        $data = false;

        $versionedNamespace = $this->getVersionedNamespace($namespace);

        $data = $this->Memcached->get($versionedNamespace . $hash);

        // If the data exists, decompress the data and return
        if ($data) {
            return Cacheable::decompressData($data);
        }

        $this->delete($hash, $namespace);

        return false;
    }

    /**
     * This function will compress data for storage in `tbl_cache`.
     * It is left to the user to define a unique hash for this data so that it can be
     * retrieved in the future. Optionally, a `$ttl` parameter can
     * be passed for this data. If this is omitted, it data is considered to be valid
     * forever. This function utilizes the Mutex class to act as a crude locking
     * mechanism.
     *
     * @see toolkit.Mutex
     * @throws DatabaseException
     * @param string $hash
     *  The hash of the Cached object, as defined by the user
     * @param string $data
     *  The data to be cached, this will be compressed prior to saving.
     * @param integer $ttl
     *  A integer representing how long the data should be valid for in minutes.
     *  By default this is null, meaning the data is valid forever
     * @param string $namespace
     *  The namespace allows data to be grouped and saved so it can be
     *  retrieved later.
     * @return boolean
     *  If an error occurs, this function will return false otherwise true
     */
    public function write($hash, $data, $ttl = null, $namespace = null)
    {

        if (!Mutex::acquire($hash, 2, TMP)) {
            return false;
        }

        if (!$data = Cacheable::compressData($data)) {
            return false;
        }

        $this->delete($hash, $namespace);

        $versionedNamespace = $this->getVersionedNamespace($namespace);

        $result = $this->Memcached->set($versionedNamespace . $hash,$data,$ttl * 60);

        if ( !$result){
            $errorCode = $this->Memcached->getResultCode();
            var_dump($errorCode);die;
        }
        
        Mutex::release($hash, TMP);

        return true;
    }

    /**
     * Given the hash of a cacheable object, remove it from memcache. 
     * If only a namespace is provided clear all caches with that namespace
     * If nothing is provided flush the whole memcache
     *
     * @throws DatabaseException
     * @param string $hash
     *  The hash of the Cached object, as defined by the user
     * @param string $namespace
     *  The namespace allows similar data to be deleted quickly.
     */
    public function delete($hash = null, $namespace = null)
    {
        if ( $hash ){
            $versionedNamespace = $this->getVersionedNamespace($namespace);

            $this->Memcached->delete($versionedNamespace . $hash);
            return true;
        }

        // very sorry memcache does not support actual data deletes for namespaces so we have a work around
        if ($namespace){
            //delete the namespace key and a new key hash will be generated on next request invalidating all keys with this namespace
            $this->Memcached->delete($namespace);
            return true;
        }

        $this->Memcached->flush();
    }
}
