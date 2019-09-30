<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  CSVUploads.SHEERData
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Save data tot eh SHEER database when CSV is uploaded.
 */
class plgCSVUploadsSHEERData extends JPlugin
{
    protected $autoloadLanguage = true;

    protected $t_db;

    /**
     * Method to instantiate the indexer adapter.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An array that holds the plugin configuration.
     *
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();

        // The following file is excluded from the public git repository (.gitignore) to prevent
        // accidental exposure of database credentials. However, you will need to create that file
        // in the same directory as this file, and it should contain the follow credentials:
        // $database = '[A]';
        // $hostname = '[B]';
        // $username = '[C]';
        // $password = '[D]';
        //
        // if you prefer to store these elsewhere, then the database_credentials.php can instead
        // require another file or indeed any other mechansim of retrieving the credentials, just so
        // long as those four variables are assigned.
        require_once('database_credentials.php');

        try {
            $this->t_db = new PDO("mysql:host=$hostname;dbname=$database", $username, $password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
            ));
        }
        catch(PDOException $e) {
            echo $e->getMessage();
            exit;
        }
    }

    /**
     * @param   array  $csv  Array holding data
     *
     * @return  mixed  Boolean true on success or String 'STOP'
     */
    public function onAfterLoadCSV($csv, $filename)
    {
        if ($filename != 'sheer-data.csv') {
            return false;
        }

        $sql = 'SELECT id FROM sheer_data';

        $stmt = $this->t_db->prepare($sql);
        $stmt->execute();

        $ids  = array();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row) {
            $ids[] = $row['id'];
        }

        // Remove first row as it's heading names:
        #array_shift( $csv );
        $sql = array();
        foreach ($csv as  $row) {

            $data = array(
                'id'          => $this->clean($row['ID']),
                'short_title' => $this->clean($row['Short Title']),
                'long_title'  => $this->clean($row['Long Title']),
                'alias'       => isset($row['Web alias']) ? $this->clean($row['Web alias']) : $this->html_id($row['Title'], 'Y'),
                'state'       => $this->clean($row['State']),
                'ordering'    => $this->clean($row['Order'])
            );

            if (in_array($row['ID'], $ids)) {
                // Update
                $id = $row['ID'];
                unset($data['id']);

                array_walk($data, function(&$value, $key){
                    $value = '`' . $key . "`=" . $value;
                });
                $sql[] = 'UPDATE `sheer_data` SET ' . implode(',', $data) . ' WHERE id = ' . $id . ";";
            } else {
                //Insert
                $sql[] = 'INSERT INTO `sheer_data` (`' . implode('`,`', array_keys($data)). '`) VALUES (' . implode(",", $data) . ');';
            }
        }
        $sql = implode("\n", $sql);

        // Take Nulls out of quotes:
        $sql  = str_replace("'Null'", "Null", $sql);
        $this->t_db->query($sql);

        try {
            $this->t_db->query($sql);
        }
        catch(PDOException $e) {
            echo $e->getMessage();
            exit;
        }

        return 'STOP';
    }
    
    /**
     * Creates an HTML-friendly string for use in id's
     *
     * @param string $text
     * @return string
     * @access public
     */
    public function html_id($text)
    {
        return "'" . strtolower(preg_replace('/\s+/', '-', trim(preg_replace('/[^a-zA-z0-9-_\s]/', '', $text)))) . "'";
    }

    /**
     * Cleans text.
     *
     * @param string $text
     * @return string
     * @access public
     */
    public function clean($text)
    {
        return "'" . trim(str_replace("'", "\'", $text)) . "'";
    }
}