<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * PDOStatement that provides some enhanced functionality needed by Propel.
 *
 * Simply adds the ability to count the number of queries executed and log the queries/method calls.
 *
 * @author     Oliver Schonrock <oliver@realtsp.com>
 * @author     Jarno Rantanen <jarno.rantanen@tkk.fi>
 * @since      2007-07-12
 * @package    propel.runtime.connection
 */
class DebugPDOStatement extends PDOStatement
{
    /**
     * The PDO connection from which this instance was created.
     *
     * @var       PropelPDO
     */
    protected $pdo;

    /**
     * Hashmap for resolving the PDO::PARAM_* class constants to their human-readable names.
     * This is only used in logging the binding of variables.
     *
     * @see       self::bindValue()
     * @var       array
     */
    protected static $typeMap = array(
        PDO::PARAM_BOOL => "PDO::PARAM_BOOL",
        PDO::PARAM_INT => "PDO::PARAM_INT",
        PDO::PARAM_STR => "PDO::PARAM_STR",
        PDO::PARAM_LOB => "PDO::PARAM_LOB",
        PDO::PARAM_NULL => "PDO::PARAM_NULL",
    );

    /**
     * @var       array  The values that have been bound
     */
    protected $boundValues = array();

    /**
     * Construct a new statement class with reference to main DebugPDO object from
     * which this instance was created.
     *
     * @param  PropelPDO         $pdo Reference to the parent PDO instance.
     * @return DebugPDOStatement
     */
    protected function __construct(PropelPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array $values Parameters which were passed to execute(), if any. Default: bound parameters.
     *
     * @return string
     */
    public function getExecutedQueryString(array $values = array()): string
    {
        $sql = $this->queryString;
        $boundValues = empty($values) ? $this->boundValues : $values;
        $matches = array();
        if (preg_match_all('/(:p[0-9]+\b)/', $sql, $matches)) {
            $size = count($matches[1]);
            for ($i = $size - 1; $i >= 0; $i--) {
                $pos = $matches[1][$i];

                // trimming extra quotes, making sure value is properly quoted afterwards
                $boundValue = $boundValues[$pos];
                if (is_string($boundValue)) { // quoting only needed for string values
                    $boundValue = trim($boundValue, "'");
                    $boundValue = $this->pdo->quote($boundValue);
                }

                if (is_resource($boundValue)) {
                    $boundValue = '[BLOB]';
                }
                if ($boundValue === null) {
                    $boundValue = '';
                }
                $sql = str_replace($pos, $boundValue, $sql);
            }
        }

        return $sql;
    }

    /**
     * Executes a prepared statement.  Returns a boolean value indicating success.
     * Overridden for query counting and logging.
     *
     * @param array|null $params
     * @return boolean
     * @throws PropelException
     */
    public function execute(?array $params = null): bool
    {
        $debug = $this->pdo->getDebugSnapshot();
        $return = parent::execute($params);

        $sql = $this->getExecutedQueryString($params ?: []);
        $this->pdo->log($sql, null, __METHOD__, $debug);
        $this->pdo->setLastExecutedQuery($sql);
        $this->pdo->incrementQueryCount();

        return $return;
    }

    /**
     * Binds a value to a corresponding named or question mark placeholder in the SQL statement
     * that was use to prepare the statement. Returns a boolean value indicating success.
     *
     * @param int|string $param Parameter identifier (for determining what to replace in the query).
     * @param mixed      $value The value to bind to the parameter.
     * @param int        $type  Explicit data type for the parameter using the PDO::PARAM_* constants. Defaults to PDO::PARAM_STR.
     *
     * @return bool
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $debug = $this->pdo->getDebugSnapshot();
        $typestr = self::$typeMap[$type] ?? '(default)';
        $return = parent::bindValue($param, $value, $type);
        $valuestr = $type == PDO::PARAM_LOB ? '[LOB value]' : var_export($value, true);
        $msg = sprintf('Binding %s at position %s w/ PDO type %s', $valuestr, $param, $typestr);

        $this->boundValues[$param] = $value;

        $this->pdo->log($msg, null, __METHOD__, $debug);

        return $return;
    }

    /**
     * Binds a PHP variable to a corresponding named or question mark placeholder in the SQL statement
     * that was use to prepare the statement. Unlike PDOStatement::bindValue(), the variable is bound
     * as a reference and will only be evaluated at the time that PDOStatement::execute() is called.
     * Returns a boolean value indicating success.
     *
     * @param int|string $param     Parameter identifier (for determining what to replace in the query).
     * @param mixed      $var       The value to bind to the parameter.
     * @param int        $type      Explicit data type for the parameter using the PDO::PARAM_* constants. Defaults to PDO::PARAM_STR.
     * @param int|null   $maxLength Length of the data type. To indicate that a parameter is an OUT parameter from a stored procedure, you must explicitly set the length.
     * @param mixed      $driverOptions
     */
    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = null,
        mixed $driverOptions = null
    ): bool {
        $originalValue = $var;
        $debug = $this->pdo->getDebugSnapshot();
        $typestr = self::$typeMap[$type] ?? '(default)';
        $return = parent::bindParam($param, $var, $type, $maxLength ?: 0, $driverOptions);
        $varstr = $maxLength > 100 ? '[Large value]' : var_export($var, true);
        $msg = sprintf('Binding %s at position %s w/ PDO type %s', $varstr, $param, $typestr);

        $this->boundValues[$param] = $originalValue;

        $this->pdo->log($msg, null, __METHOD__, $debug);

        return $return;
    }
}
