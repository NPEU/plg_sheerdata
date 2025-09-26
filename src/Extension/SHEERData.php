<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  CSVUploads.SHEERData
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\CSVUploads\SHEERData\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Save data to the SHEER database when CSV is uploaded.
 */
class SHEERData extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $t_db;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);

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
        require_once(realpath(dirname(dirname(__DIR__))) . '/database_credentials.php');

        try {
            $this->t_db = new \PDO("mysql:host=$hostname;dbname=$database", $username, $password, [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
            ]);
        }
        catch(\PDOException $e) {
            echo $e->getMessage();
            exit;
        }
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onAfterLoadCSV' => 'onAfterLoadCSV',
        ] : [];
    }

    /**
     * @param   array  $csv  Array holding data
     *
     * @return  string 'STOP'
     */
    public function onAfterLoadCSV(Event $event): string
    {
        [$csv, $filename] = array_values($event->getArguments());

        if ($filename != 'sheer-data.csv') {
            return false;
        }

        $sql = 'SELECT id FROM sheer_data';

        $stmt = $this->t_db->prepare($sql);
        $stmt->execute();

        $ids  = [];
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($rows as $row) {
            $ids[] = $row['id'];
        }

        // Remove first row as it's heading names:
        #array_shift( $csv );
        $sql = [];
        foreach ($csv as  $row) {

            $data = [
                'id'          => $this->clean($row['ID']),
                'short_title' => $this->clean($row['Short Title']),
                'long_title'  => $this->clean($row['Long Title']),
                'alias'       => isset($row['Web Alias']) ? $this->clean($row['Web Alias']) : $this->html_id($row['Short Title'], 'Y'),
                'state'       => $this->clean($row['State']),
                'ordering'    => $this->clean($row['Order'])
            ];

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
        #$this->t_db->query($sql);

        try {
            $this->t_db->query($sql);
        }
        catch(\PDOException $e) {
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