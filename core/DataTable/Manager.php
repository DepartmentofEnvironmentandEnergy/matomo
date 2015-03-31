<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\DataTable;

use Exception;
use Piwik\Common;
use Piwik\DataTable;

/**
 * The DataTable_Manager registers all the instanciated DataTable and provides an
 * easy way to access them. This is used to store all the DataTable during the archiving process.
 * At the end of archiving, the ArchiveProcessor will read the stored datatable and record them in the DB.
 */
class Manager extends \ArrayObject
{
    /**
     * Id of the next inserted table id in the Manager
     * @var int
     */
    protected $nextTableId = 1;

    private static $instance;

    public static function getInstance() {

        if (!isset(self::$instance)) {
            self::$instance = new Manager();
        }

        return self::$instance;
    }

    /**
     * Add a DataTable to the registry
     *
     * @param DataTable $table
     * @return int  Index of the table in the manager array
     */
    public function addTable($table)
    {
        $this[$this->nextTableId] = $table;
        $this->nextTableId++;
        return $this->nextTableId - 1;
    }

    /**
     * Returns the DataTable associated to the ID $idTable.
     * NB: The datatable has to have been instanciated before!
     * This method will not fetch the DataTable from the DB.
     *
     * @param int $idTable
     * @throws Exception If the table can't be found
     * @return DataTable  The table
     */
    public function getTable($idTable)
    {
        if (!isset($this[$idTable])) {
            throw new TableNotFoundException(sprintf("Error: table id %s not found in memory. (If this error is causing you problems in production, please report it in Piwik issue tracker.)", $idTable));
        }

        return $this[$idTable];
    }

    /**
     * Returns the latest used table ID
     *
     * @return int
     */
    public function getMostRecentTableId()
    {
        return $this->nextTableId - 1;
    }

    /**
     * Delete all the registered DataTables from the manager
     */
    public function deleteAll($deleteWhenIdTableGreaterThan = 0)
    {
        foreach ($this as $id => $table) {
            if ($id > $deleteWhenIdTableGreaterThan) {
                $this->deleteTable($id);
            }
        }

        if ($deleteWhenIdTableGreaterThan == 0) {
            $this->exchangeArray(array());
            $this->nextTableId = 1;
        }
    }

    /**
     * Deletes (unsets) the datatable given its id and removes it from the manager
     * Subsequent get for this table will fail
     *
     * @param int $id
     */
    public function deleteTable($id)
    {
        if (isset($this[$id])) {
            Common::destroy($this[$id]);
            $this->setTableDeleted($id);
        }
    }

    /**
     * Deletes all tables starting from the $firstTableId to the most recent table id except the ones that are
     * supposed to be ignored.
     *
     * @param int[] $idsToBeIgnored
     * @param int $firstTableId
     */
    public function deleteTablesExceptIgnored($idsToBeIgnored, $firstTableId = 0)
    {
        $lastTableId = $this->getMostRecentTableId();

        for ($index = $firstTableId; $index <= $lastTableId; $index++) {
            if (!in_array($index, $idsToBeIgnored)) {
                $this->deleteTable($index);
            }
        }
    }

    /**
     * Remove the table from the manager (table has already been unset)
     *
     * @param int $id
     */
    public function setTableDeleted($id)
    {
        $this[$id] = null;
    }

    /**
     * Debug only. Dumps all tables currently registered in the Manager
     */
    public function dumpAllTables()
    {
        echo "<hr />Manager->dumpAllTables()<br />";
        foreach ($this as $id => $table) {
            if (!($table instanceof DataTable)) {
                echo "Error table $id is not instance of datatable<br />";
                var_export($table);
            } else {
                echo "<hr />";
                echo "Table (index=$id) TableId = " . $table->getId() . "<br />";
                echo $table;
                echo "<br />";
            }
        }
        echo "<br />-- End Manager->dumpAllTables()<hr />";
    }
}
