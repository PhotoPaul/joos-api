<?

class DBController{
    public $dbObject;
    public $hasher;

    function __construct(DBSettings $dbSettings){
        try {
            $this->dbObject = new PDO("mysql:host=".$dbSettings->host.";port=".$dbSettings->port.";dbname=".$dbSettings->db, $dbSettings->username, $dbSettings->password, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8;"]);
            $this->dbObject->exec("SET time_zone = '".$dbSettings->timezone."'");
            $this->dbObject->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); // Error Handling
            $this->dbObject->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true ); // Fetches "1" instead of 1
        } catch(Exception $e) {
            dbg(new AjaxError(__METHOD__.': '.$e->getMessage()));
        }

        $this->hasher = new PasswordHash(8,true);
    }

    function sql1($args) {
        return $this->sql($args, true);
    }

    function sql($args, $singleRow = false) {
        $args = (object) $args;
        $sql = [$args->statement];
        $params = [];
        
        if ($sql[0] === 'SELECT') {
            // Columns
            $selectString = $this->getSelectString($args->columns);
            array_push($sql, $selectString);
            // Table
            if (isset($args->table)) {
                array_push($sql, 'FROM '.$args->table);
            }
            // Joins
            if (isset($args->joins)) {
                $joinsString = $this->getJoinsString($args->joins);
                array_push($sql, $joinsString);
            }
            // Where
            if (isset($args->where)) {
                $whereStringValues = $this->getWhereStringValues($args->where);
                array_push($sql, $whereStringValues->string);
                if (isset($whereStringValues->values)) {
                    $params = array_merge($params, $whereStringValues->values);
                }
            }
            // Group By
            if (isset($args->group)) {
                array_push($sql, 'GROUP BY');
                array_push($sql, $args->group);
            }
            // Order By
            if (isset($args->order)) {
                array_push($sql, 'ORDER BY');
                array_push($sql, $args->order);
            }
            // Extra Params
            if (isset($args->extras)) {
                $params = array_merge($params, $args->extras);
            }
            $sql = implode(' ', $sql);
            $statement = $this->dbObject->prepare($sql);
            $statement->execute(count($params) ? $params : null);
            $rows = $singleRow ?
                $statement->fetch(PDO::FETCH_OBJ) :
                $statement->fetchAll(PDO::FETCH_OBJ);
            return $rows;
        } elseif ($sql[0] === 'DELETE FROM') {
            // Table
            array_push($sql, $args->table);
            // Where
            if (isset($args->where)) {
                $whereStringValues = $this->getWhereStringValues($args->where);
                array_push($sql, $whereStringValues->string);
                if (isset($whereStringValues->values)) {
                    $params = array_merge($params, $whereStringValues->values);
                }
            }
            $sql = implode(' ', $sql);
            $statement = $this->dbObject->prepare($sql);
            $result = $statement->execute(count($params) ? $params : null);

            return (object) [
                'success' => $result,
                'rowCount' => $statement->rowCount() // If 'update' then rowCount returns 1 if inserted and 2 if updated
            ];
        } elseif (substr($sql[0], 0, 6) === 'UPDATE') {
            // Table
            array_push($sql, isset($args->table) ? $args->table : null);
            // Joins
            if (isset($args->joins)) {
                $joinsString = $this->getJoinsString($args->joins);
                array_push($sql, $joinsString);
            }
            // Columns
            if (isset($args->columns)) {
                $updateStringValues = $this->getUpdateStringValues($args->columns, $args->values);
                array_push($sql, $updateStringValues->string);
            }
            if (isset($updateStringValues->values)) {
                if (!is_array($updateStringValues->values)) {
                    $updateStringValues->values = [$updateStringValues->values];
                }
                $params = array_merge($params, $updateStringValues->values);
            }
            // Where
            if (isset($args->where)) {
                $whereStringValues = $this->getWhereStringValues($args->where);
                array_push($sql, $whereStringValues->string);
                if (isset($whereStringValues->values)) {
                    $params = array_merge($params, $whereStringValues->values);
                }
            }
            $sql = implode(' ', $sql);
            $statement = $this->dbObject->prepare($sql);
            $result = $statement->execute(count($params) ? $params : null);

            return (object) [
                'success' => $result,
                'rowCount' => $statement->rowCount() // If 'update' then rowCount returns 1 if inserted and 2 if updated
            ];
        } elseif ($sql[0] === 'INSERT INTO') {
            // Table
            array_push($sql, $args->table);
            // Columns
            if (isset($args->columns)) {
                if (isset($args->values)) {
                    $insertStringValues = $this->getInsertStringValues($args->columns, $args->values);
                    if (!is_array($insertStringValues->values)) {
                        $insertStringValues->values = [$insertStringValues->values];
                    }
                    $params = array_merge($params, $insertStringValues->values);
                } else {
                    $insertStringValues = $this->getInsertStringValues($args->columns);
                }

                array_push($sql, $insertStringValues->string);
            }
            // SELECT instead of VALUES
            if (isset($args->select)) {
                array_push($sql, $args->select);
            }
            // Extra Params
            if (isset($args->extras)) {
                $params = array_merge($params, $args->extras);
            }
            if (isset($args->update)) {
                $updateStringValues = $this->getUpdateStringValues($args->columns, $args->values, true);
                array_push($sql, $updateStringValues->string);
                if (!is_array($updateStringValues->values)) {
                    $updateStringValues->values = [$updateStringValues->values];
                }
                $params = array_merge($params, $updateStringValues->values);
            }
            $sql = implode(' ', $sql);
            $statement = $this->dbObject->prepare($sql);
            $result = $statement->execute(count($params) ? $params : null);

            return (object) [
                'success' => $result,
                'lastInsertId' => $this->dbObject->lastInsertId(),
                'rowCount' => $statement->rowCount() // If 'update' then rowCount returns 1 if inserted and 2 if updated
            ];
        } else {
            // Where
            if (isset($args->where)) {
                $whereStringValues = $this->getWhereStringValues($args->where);
                array_push($sql, $whereStringValues->string);
                if (isset($whereStringValues->values)) {
                    $params = array_merge($params, $whereStringValues->values);
                }
            }
            // Extra Params
            if (isset($args->extras)) {
                $params = array_merge($params, $args->extras);
            }
            $sql = implode(' ', $sql);
            $statement = $this->dbObject->prepare($sql);
            $statement->execute(count($params) ? $params : null);

            return (object) [
                'success' => isset($result) ? $result : null,
                'lastInsertId' => $this->dbObject->lastInsertId(),
                'rowCount' => $statement->rowCount() // If 'update' then rowCount returns 1 if inserted and 2 if updated
            ];
        }
    }

    function groupResults($results, $primaryKey, $groups = []) {
        // Ensure $groups is an Array
        if (is_array($groups) && !is_array($groups[0])) {
            $groups = [$groups];
        }
        // Ensure each $group is an Array
        foreach ($groups as $group) {
            if (!is_array($group)) {
                $group = [$group];
            }
        }

        // Initialize loop
        $firstIndex = null;
        $noResults = count($results);

        // Loop through $results
        for($i = 0; $i < $noResults; $i++) {
            if (!is_null($firstIndex) && $results[$firstIndex]->{$primaryKey} === $results[$i]->{$primaryKey}) {
                foreach ($groups as $group) {
                    if (is_array($group[1]) && count($group[1]) > 1) {
                        // Save as object
                        $obj = new stdClass();
                        if (!is_array($group[1])) {
                            $group[1] = [$group[1]];
                        }
                        foreach($group[1] as $groupItem) {
                            // Only add property if it has a value
                            if (isset($results[$i]->{$groupItem})) {
                                $obj->{$groupItem} = $results[$i]->{$groupItem};
                            }
                        }
                        // Check kept items for a duplicate before adding
                        if(count(get_object_vars($obj))) {
                            $found = false;
                            foreach($results[$firstIndex]->{$group[0]} as $savedItem) {
                                if ($savedItem == $obj) {
                                    $found = true;
                                    break;
                                }
                            }
                            if ($found === false) {
                                array_push($results[$firstIndex]->{$group[0]}, $obj);
                            }
                        }
                    } else {
                        if (!in_array($results[$i]->{$group[1]}, $results[$firstIndex]->{$group[0]})) {
                            // Save as item
                            array_push($results[$firstIndex]->{$group[0]}, $results[$i]->{$group[1]});
                        }
                    }
                }
                unset($results[$i]);
            } else {
                $firstIndex = $i;
                // Initialize first row
                foreach ($groups as $group) {
                    // Initialize array
                    $results[$firstIndex]->{$group[0]} = []; // group[0] is array name
                    if (is_array($group[1]) && count($group[1]) > 1) {
                        // Save as object
                        $obj = new stdClass();
                        if (!is_array($group[1])) {
                            $group[1] = [$group[1]];
                        }
                        foreach($group[1] as $groupItem) {
                            if ($results[$i]->{$groupItem} !== null) {
                                $obj->{$groupItem} = $results[$i]->{$groupItem};
                            }
                            unset($results[$i]->{$groupItem});
                        }
                        if(count(get_object_vars($obj))) {
                            array_push($results[$firstIndex]->{$group[0]}, $obj);
                        }
                    } else {
                        // Save as item
                        array_push($results[$firstIndex]->{$group[0]}, $results[$firstIndex]->{$group[1]});
                        unset($results[$firstIndex]->{$group[1]});    
                    }
                }
            }
        }
        $results = array_values($results);
        return $results;        
    }

    function getWhereStringValues ($where = null, $isMandatory = false) {
        if (!isset($where) && $isMandatory) {
            die('WHERE is Mandatory');
        }
        $whereString = ['WHERE'];
        if (!is_array($where)) {
            $where = [$where];
        }
        array_push($whereString, $where[0]);
        $whereString = implode(' ', $whereString);
        if (isset($where[1])) {
            if (!is_array($where[1])) {
                $where[1] = [$where[1]];
            }
        }
        return (object) [
            'string' => $whereString,
            'values' => isset($where[1]) ? $where[1] : null
        ];
    }

    function getInsertStringValues ($columns, $values = null) {
        if (!is_array($columns)) {
            $columnsArray = explode(',', $columns);
        } else {
            $columnsArray = $columns;
        }
        $noColumns = count($columnsArray);
        if (isset($values)) {
            $insertString = [];
            $insertValuesString = [];
            for ($i = 0; $i < $noColumns; $i++) {
                array_push($insertString, $columnsArray[$i]);
                array_push($insertValuesString, '?');
            }
            $insertString = '('.implode(',', $insertString).') VALUES ('.implode(',', $insertValuesString).')';
        } else {
            $insertString = [];
            for ($i = 0; $i < $noColumns; $i++) {
                array_push($insertString, $columnsArray[$i]);
            }
            $insertString = '('.implode(',', $insertString).')';
        }

        return (object) [
            'string' => $insertString,
            'values' => $values
        ];
    }

    function getJoinsString ($joins) {
        if (!is_array($joins)) {
            $joins = [$joins];
        }
        $joinsString = implode(' ', $joins);
        return $joinsString;
    }

    function getSelectString ($columns) {
        if (!is_array($columns)) {
            $columnsArray = explode(',', $columns);
        } else {
            $columnsArray = $columns;
        }
        $noColumns = count($columnsArray);
        $selectString = [];
        for ($i = 0; $i < $noColumns; $i++) {
            array_push($selectString, $columnsArray[$i]);
        }
        $selectString = implode(',', $selectString);
        return $selectString;
    }

    function getUpdateStringValues ($columns, $values, $fromInsert = false) {
        if (!isset($columns)) return;
        if (!is_array($columns)) {
            $columnsArray = explode(',', $columns);
        } else {
            $columnsArray = $columns;
        }
        $noColumns = count($columnsArray);
        $updateString = [];
        if ($fromInsert) {
            $offset = 1;
            if (is_array($values) && count($values) > 1) {
                $updateHead = 'ON DUPLICATE KEY UPDATE';
                $values = array_splice($values, 1);
            } else {
                $updateHead = '';
                $values = [];
            }
        } else {
            $offset = 0;
            $updateHead = 'SET';
        }
        if ($noColumns > $offset) {
            for ($i = $offset; $i < $noColumns; $i++) {
                array_push($updateString, $columnsArray[$i].' = ?');
            }
        }
        $updateString = implode(',', $updateString);

        return (object) [
            'string' => $updateHead.' '.$updateString,
            'values' => $values
        ];
    }

    function getVariables($whereParams) {
        $whereString = ['FALSE'];
        foreach($whereParams as $param) {
            array_push($whereString, 'name = ?');
        }
        $where = [implode(' OR ', $whereString), $whereParams];

        $values = $this->sql([
            'statement' => 'SELECT',
            'columns' => 'type, value',
            'table' => 'admin_variables',
            'where' => $where
        ]);

        $variables = new stdClass();
        for($i = 0; $i < count($values); $i++) {
            $variables->{$whereParams[$i]} = $values[$i]->type === "number" ? (int)$values[$i]->value : $values[$i]->value;
        }

        return $variables;
    }
}