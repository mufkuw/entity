<?php

namespace Entities;

global $entities_class_index;

abstract class Entity {

	public static $sql;
	public static $setup;
	private static $associations = [];
	private static $instances = [];
	private $table_name = '';
	private $model_class = '';
	private $key_column = 'cID';
	private $insert_sql;
	private $update_sql;
	private $alerts = [];
	private static $tables = null;

	/* Getting Entities Instances - Entities::entity_name();
	 * ***************************************************************************
	 * eg : Instance of AccountsEntity be like Entities::accounts();
	 * eg : Instance of JournalRowsEntity be like Entities::journal_rows();
	 * eg : Instance of JournalHeadersEntity be like Entities::journal_headers();
	 * eg : Instance of ProductsEntity be like Entities::products();
	 *
	 * *************************************************************************** */


	/* Getting Association Model Instances
	 * ***************************************************************************
	 * eg : Journal Headers entity contains cCustomerID as a foreign association to the Customers entity
	 *
	 *      - To get the instance of Customer Model from the Journal Headers entity be like
	 *
	 *      ONE TO ONE ASSOCIATION
	 *      Entity::journal_headers()->getCustomer($journal_header_id);
	 *      // Returns the Customer Model for a the Journal Header Record identified as  $journal_header_id
	 *
	 *      - To get the instance of ALL the journal header where the customer association is used from the Customer entity be like
	 *
	 *      ONE TO MANY ASSOCIATION
	 *      Entity::customers()->getJournalHeaders($customer_id);
	 *      // Returns all the journal headers where the $customer_id is associated
	 *
	 *
	 * eg : Journal Rows entity contains cJournalHeader as a foreign association to the Journal Headers entity
	 *
	 *      - To get the instance of Journal Header Model from the Journal Rows entity be like
	 *
	 *      ONE TO ONE ASSOCIATION
	 *      Entity::journal_rows()->getJournalHeader($journal_row_id);
	 *      // Returns the Journal Header Model for a the Journal Row Record identified as  $journal_row_id
	 *
	 *      - To get the instance of ALL the journal rows where the journal header id association is used from the Journal Headers entity be like
	 *
	 *      ONE TO MANY ASSOCIATION
	 *      Entity::journal_headers()->getJournalRows($journal_header_id);
	 *      // Returns all the journal rows where the $journal_header_id is associated
	 *
	 *
	 *
	 * *************************************************************************** */



	/* Getting Agregates of a Field in an Entity
	 * ***************************************************************************
	 * eg: getting the aggregates of the fields in an Entity
	 *
	 *     Entity->journal_rows()->getSumOfAmount('where clause');               -    Getting sum of Amount from journal rows with the where clause
	 *     Entity->journal_rows()->getMinOfCp('where clause');                   -    Getting min of CP from journal rows with the where clause
	 *     Entity->journal_rows()->getCountOfProductID('where clause');          -    Getting count of product id from journal rows with the where clause
	 *     Entity->journal_rows()->getSumOf('cSp*cCp','where clause');           -    Getting the some of cp*sp from journal rows with the where clause
	 *
	 * */

	//***HOOKS****//
	//	tablename.creating
	//	tablename.created
	//	tablename.opening
	//	tablename.opened
	//	tablename.saving
	//	tablename.saved
	//	tablename.updating
	//	tablename.updated
	//***********//

	public function __construct() {
		$class = get_called_class();
		$this->table_name = EntityInflector::delimit(substr($class, 0, -6));
		$this->model_class = EntityInflector::singularize(substr($class, 0, -6)) . 'Model';

		$s1 = '';
		$s2 = '';
		$s3 = '';

		$infodata = $this->get_table_info();

		foreach ($infodata as $row) {
			$s1 .= ',' . $row['column_name'];
			$s2 .= ',' . $row['column_name'] . '=' . '?';
			$s3 .= ',?';
		}

		$s1 = substr($s1, 1);
		$s2 = substr($s2, 1);
		$s3 = substr($s3, 1);

		$this->insert_sql = 'insert into ' . $this->table_name . ' (' . $s1 . ') values (' . $s3 . ')';
		$this->update_sql = 'update ' . $this->table_name . ' set ' . $s2 . ' where ' . $this->key_column . '= ?';
	}

	// <editor-fold defaultstate="collapsed" desc="Private Static Functions">
	public static function instance() {
		$class = get_called_class();
		if (!isset(self::$instances[$class])) {
			self::$instances[$class] = false;
		}

		if (!self::$instances[$class])
			self::$instances[$class] = new $class();

		return self::$instances[$class];
	}

	public static function init($pSetup) {

		self::setup_pdo($pSetup);

		self::$setup = $pSetup;

		$tables = self::get_tables();

		self::generate_entities_code($tables);
	}

	private static function generate_entities_code($tables) {

		global $entities_class_index;

		$pSetup = self::$setup;

		$file_entity_base = $pSetup['cache_path'] . '/Entities.php';

		if (self::index_exists($file_entity_base)) {
			//require_once(realpath($file_entity_base));
			$entities_class_index['Entities'] = $file_entity_base;
		} else {
			$code = "";
			// <editor-fold defaultstate="collapsed" desc="Entity Base Codes, Replace \\[CLASS_INSTANCE_FUNCTIONS]">
			$code .= "<?php\n\n
			class Entities {

				private static function getEntityInstance(\$name)
				{
					try {
						\$class = str_replace('_', '', ucwords(strtolower(\$name), '_')) . 'Entity';
						if (class_exists(\$class)) {
							\$instance = \$class::instance();

							if (isset(\$args[0]) && is_numeric(\$args[0]))
								return \$instance->open(\$args[0]); //this will return the model if there is an argument of a numeric type

							return \$instance;
						}
						else {
							return null;
						}
					} catch (Exception \$e) {
						return null;
					}
				}

				\\[CLASS_INSTANCE_FUNCTIONS]

			}";

// </editor-fold>

			$function_code = "";
			foreach ($tables as $table) {
				$function_code .= "/**
									* @return " . EntityInflector::camelize($table) . "Entity
									*/
									public static function $table() { return self::getEntityInstance('$table');}\n";
			}

			$code = str_replace("\\[CLASS_INSTANCE_FUNCTIONS]", $function_code, $code);

			self::code_generate($code, $file_entity_base);
			//require_once(realpath($file_entity_base));
			$entities_class_index['Entities'] = $file_entity_base;
		}

		$association = [];
		$file_association = $pSetup['cache_path'] . '/TableAssociation.php';

		$file_association_exists = self::index_exists($file_association);

		if ($file_association_exists) {
			self::$associations = self::index_get($file_association);
		}

		foreach ($tables as $table) {

			$modelname = ucwords(EntityInflector::singularize(EntityInflector::camelize($table)));
			$entityname = ucwords(EntityInflector::camelize($table));

			$file = $pSetup['cache_path'] . '/TableInfo' . ucwords($entityname) . '.php';
			if (self::index_exists($file)) {
				$table_desc = self::index_get($file);
			} else {
				$table_desc = self::select("select column_name,column_default FROM information_schema.COLUMNS where TABLE_SCHEMA='" . $pSetup['database'] . "' AND TABLE_NAME='" . $table . "'");
				self::index_generate($table_desc, $file);
			}

			$this_table_associations = [];

			if (!$file_association_exists) {
				foreach ($table_desc as $row) {
					$column = $row['column_name'];
					$method = self::getMethodNameRegEX($column, '/c(.*)ID/');

					$calling_entity = $entityname . 'Entity';
					if ($method) {
						$field = self::camel_split($method[0]);
						$a = '';
						if ($column != 'cID') {
							for ($i = count($field) - 1; $i >= 0; $i--) {
								$a = $field[$i] . $a;
								$entityClass = EntityInflector::pluralize($a) . 'Entity';
								$table_name = EntityInflector::delimit(EntityInflector::pluralize($a));
								if (array_search(strtolower($table_name), $tables)) {
									self::associate($calling_entity, $column, $entityClass, 'cID');
								}
							}
						}
					}
				}
			}
		}

		if ($file_association_exists) {
			self::$associations = self::index_get($file_association);
		} else {
			self::index_generate(self::$associations, $file_association);
		}

		foreach ($tables as $table) {
			$model_name = ucwords(EntityInflector::singularize(EntityInflector::camelize($table)));
			$model_class_name = $model_name . 'Model';
			$entity_name = ucwords(EntityInflector::camelize($table));
			$entity_class_name = $entity_name . 'Entity';
			$auto_gen_entity_class_name = 'AutoGen' . $entity_class_name;
			$auto_gen_model_class_name = 'AutoGen' . $model_class_name;

			$file_table_info = $pSetup['cache_path'] . '/TableInfo' . ucwords($entity_name) . '.php';
			$table_desc = self::index_get($file_table_info);

			$file = $pSetup['cache_path'] . "/$auto_gen_model_class_name.php";
			if (self::index_exists($file)) {
				$entities_class_index[$auto_gen_model_class_name] = $file;
				//require_once(realpath($file));
			} else {
				$code = "<?php class $auto_gen_model_class_name extends Model {\n";
				foreach ($table_desc as $column) {
					$code .= "\n\tpublic \${$column['column_name']} = '{$column['column_default']}';";
				}
				$code .= "\n\n";
				if (isset(self::$associations[$entity_class_name])) {
					foreach (self::$associations[$entity_class_name] as $column => $assocation) {

						if (substr($column, 0, 1) == 'c' && substr($column, -2, 2) == 'ID') {
							$func_name = 'get' . substr($column, 1, -2);
							$object = $assocation;
							$type = 'column';
						}

						if (substr($column, -6, 6) == 'Entity') {
							$func_name = 'get' . substr($column, 0, -6);
							$entity = $entity_class_name;
							$object = $assocation;
							$type = 'entity';
						}



						$object_code = self::index_array_to_code($object);

						$code .= "\n\tpublic function $func_name()\n"
								. "\t{\n"
								. "\t\t\$entity = '$entity_class_name';\n"
								. "\t\t\$cID = \$this->cID;\n"
								. "\t\t\$object = $object_code;\n"
								. "\t\t\$type = '$type';\n"
								. "\t\treturn \$entity::instance()->getAssociationValue(['entity'=>\$entity,'id'=> \$cID, 'association'=>\$object, 'type'=>\$type]);\n"
								. "\t}\n";
					}
				}

				$code .= '}';
				self::code_generate($code, $file);
				$entities_class_index[$auto_gen_model_class_name] = $file;
			}


			$file = $pSetup['cache_path'] . "/$auto_gen_entity_class_name.php";
			if (self::index_exists($file)) {
				$entities_class_index[$auto_gen_entity_class_name] = $file;
			} else {
				$code = "<?php class $auto_gen_entity_class_name extends Entity { ";

				if (isset(self::$associations[$entity_class_name])) {
					foreach (self::$associations[$entity_class_name] as $column => $assocation) {

						if (substr($column, 0, 1) == 'c' && substr($column, -2, 2) == 'ID') {
							$func_name = 'get' . substr($column, 1, -2);
							$object = $assocation;
							$param_name = 'c' . EntityInflector::camelize(($model_name)) . 'ID';
							$type = 'column';
						}

						if (substr($column, -6, 6) == 'Entity') {
							$func_name = 'get' . substr($column, 0, -6);
							$entity = $entity_class_name;
							$param_name = 'c' . EntityInflector::camelize(($model_name)) . 'ID';
							$object = $assocation;
							$type = 'entity';
						}



						$object_code = self::index_array_to_code($object);



						$code .= "\n\tpublic function $func_name(\$$param_name)\n"
								. "\t{\n"
								. "\t\t\$entity = '$entity_class_name';\n"
								. "\t\t\$object = $object_code;\n"
								. "\t\t\$type = '$type';\n"
								. "\t\treturn \$this->getAssociationValue(['entity'=>\$entity,'id'=> \$$param_name, 'association'=>\$object, 'type'=>\$type]);\n"
								. "\t}\n";
					}
				}

				$code .= "/**"
						. "*@return $model_class_name"
						. "*/\n"
						. "\n\tpublic function open(\$pID)\n"
						. "\t{\n"
						. "\t\treturn parent::open(\$pID);\n"
						. "\t}\n";

				$code .= "/**"
						. "*@return $model_class_name"
						. "*/\n"
						. "\n\tpublic function save(&\$pModel)\n"
						. "\t{\n"
						. "\t\treturn parent::save(\$pModel);\n"
						. "\t}\n";


				$code .= "/**"
						. "*@return $model_class_name"
						. "*/\n"
						. "\n\tpublic function create(\$pForcedID = 0, \$pOverride = false)\n"
						. "\t{\n"
						. "\t\treturn parent::create(\$pForcedID, \$pOverride);\n"
						. "\t}\n";


				$code .= '}';
				self::code_generate($code, $file);

				$entities_class_index[$auto_gen_entity_class_name] = $file;
			}

			$file = $pSetup['entities_path'] . "/$entity_class_name.php";
			if (self::index_exists($file)) {
				$entities_class_index[$entity_class_name] = $file;
			} else {
				$code = "<?php class $entity_class_name extends $auto_gen_entity_class_name {\n}";
				self::code_generate($code, $file);
				$entities_class_index[$entity_class_name] = $file;
			}



			$file = $pSetup['models_path'] . "/$model_class_name.php";
			if (self::index_exists($file)) {
				$entities_class_index[$model_class_name] = $file;
			} else {

				$code = "<?php class $model_class_name extends $auto_gen_model_class_name {\n}";
				self::code_generate($code, $file);
				$entities_class_index[$model_class_name] = $file;
			}
		}
	}

	private static function associate($pCallingEntity, $pEntityColumn, $pAssociatedEntityName, $pAssociatedEntityColumn) {
		self::$associations[$pCallingEntity][$pEntityColumn] = ['key' => $pEntityColumn, 'object' => $pAssociatedEntityName, 'column' => $pAssociatedEntityColumn];
		self::$associations[$pAssociatedEntityName][$pCallingEntity][$pEntityColumn] = ['key' => 'cID', 'object' => $pCallingEntity, 'column' => $pEntityColumn];
	}

	private static function camel_split($string) {
		if (!$string)
			return false;
		$s = preg_split('/(?=[A-Z]\w+)/', $string);
		return $s;
	}

	private static function get_tables() {
		//Collecting Table Info
		$file = self::$setup['cache_path'] . '/EntityTables.php';

		if (!self::$tables) {
			if (self::index_exists($file)) {
				$tables = self::index_get($file);
				self::$tables = $tables;
			} else {
				$tables = self::select("select distinct TABLE_NAME from information_schema.COLUMNS where TABLE_SCHEMA='" . self::$setup['database'] . "' and COLUMN_NAME='cID'");

				$ret_tables = [];
				foreach ($tables as $table) {
					if (EntityInflector::singularize($table['TABLE_NAME']) != $table['TABLE_NAME']) {
						$ret_tables[] = $table['TABLE_NAME'];
					}
				}
				$tables = $ret_tables;
				self::$tables = $tables;
				self::index_generate($ret_tables, $file);
			}
		} else {
			return self::$tables;
		}

		return $tables;
	}

	private static function setup_pdo($pSetup) {
//echo "mysql:host={$pSetup['server']};dbname={$pSetup['database']};";
		self::$sql = new PDO("mysql:host={$pSetup['server']};dbname={$pSetup['database']};", $pSetup['username'], $pSetup['password']);
		self::$sql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$sql->exec("SET CHARACTER SET utf8");
	}

	private static function getMethodNameRegEX($name, $pPattern) {
		$matches = [];
		preg_match_all($pPattern, $name, $matches);

		if ($matches[0] && count($matches) > 1) {
			$arr = [];
			array_shift($matches);
			foreach ($matches as $match) {
				$arr[] = $match[0];
			}

			return $arr;
		} else
			return false;
	}

	// </editor-fold>
	//
	//
	// <editor-fold defaultstate="collapsed" desc="Public Get Information Functions">


	protected function getAssociationValue($args) {
		if ($args['type'] == 'column') {
			$association = $args['association'];

			$table_assoc = strtolower(substr($association['object'], 0, -6));
			$sql = "select * from $table_assoc where cID in (select {$association['key']} from {$this->table_name} where cID = '{$args['id']}' )";
			$data = self::select($sql);
			return ($data);
		}
		if ($args['type'] == 'entity') {
			foreach ($args['association'] as $association) {
				$where[] = "{$association['column']}  = '{$args['id']}'";
			}
			$where = implode(" OR ", $where);
			$object = $association['object']::instance();
			return $object->get($where, '*');
		}
	}

	public function get($pWhere = "1=1", $pFields = '*', $params = null, $start = 0, $length = 0) {
		$sql = "select $pFields from $this->table_name where $pWhere";
		return self::select($sql, NULL, 0, 0, $this->model_class);
	}

	public function getByID($pID) {
		$data = $this->get("cID=$pID");
		if ($data && count($data) > 0)
			return $data[0];
	}

	/**
	 * Getting Aggregates like sum, max, min, avg on a field specified
	 * also can be called from __call as getFieldSumOf('cField','pWhere')
	 * @param mixed $pAggregate
	 * @param mixed $pField
	 * @param mixed $pWhere
	 * @return mixed
	 */
	public function getAggregateOf($pAggregate, $pField, $pWhere = '1=1') {

		if ($pWhere || $pWhere == '')
			$pWhere = '1=1';
		return self::scalar("select $pAggregate($pField) from $this->table_name where $pWhere");
	}

	public function getSumOf($pField, $pWhere = '1=1') {
		return $this->getAggregateOf('sum', $pField, $pWhere);
	}

	public function getMinOf($pField, $pWhere = '1=1') {
		return $this->getAggregateOf('min', $pField, $pWhere);
	}

	public function getMaxOf($pField, $pWhere = '1=1') {
		return $this->getAggregateOf('min', $pField, $pWhere);
	}

	public function getAvgOf($pField, $pWhere = '1=1') {
		return $this->getAggregateOf('avg', $pField, $pWhere);
	}

	//__call method of entity for associations on object instance
	public function __call($method, $args) {
		//getting field aggregate
		if (($aggregates = self::getMethodNameRegEX($method, '/get(.*)Of(.*)/'))) {

			if ($aggregates[1] == '') {
				array_pop($aggregates);
			}

			$aggregates = array_merge($aggregates, $args);

			if (substr($aggregates[1], 0, 1) != 'c') {
				$aggregates[1] = 'c' . $aggregates[1];
			}
			return call_user_func_array([$this, 'getAggregateOf'], $aggregates);
		}
	}

	// </editor-fold>
	//
	//
	// <editor-fold defaultstate="collapsed" desc="private Sql Functions">

	private static function select($sql, $params = false, $start = 0, $length = 0, $model = '') {

		$pdo = self::$sql;

		if ($start == 0 && $length == 0)
			$resource = $pdo->prepare($sql);
		else {
			$resource = $pdo->prepare("select * from ($sql) as query limit $start,$length");
		}

		if ($params && count($params) > 0)
			for ($i = 0; $i < count($params); $i++)
				$resource->bindParam($i + 1, $params[$i]);


		if ($resource->execute()) {
			$data = array();
			if ($model == '')
				$data = $resource->fetchAll(PDO::FETCH_ASSOC);
			else
				$data = $resource->fetchAll(PDO::FETCH_CLASS, $model);
			$resource->closeCursor();
			$resource = null;
			return $data;
		} else {
			throw new Exception($resource->errorInfo() . '\n' . $sql . '\n' . $params);
		}
	}

	private static function singular($sql, $params = false, $model = '') {
		return self::select($sql, $params, 0, 1, $model);
	}

	private static function scalar($query, $params = false) {
		$data = self::singular($query, $params);
		if ($data && count($data) > 0) {
			$values = array_values($data[0]);
			if (count($values) > 0)
				return $values[0];
		} else
			return false;
	}

	private static function execute($sql, $params = false) {
		$pdo = self::$sql;

		$resource = $pdo->prepare($sql);

		if ($params && count($params) > 0)
			for ($i = 0; $i < count($params); $i++)
				$resource->bindParam($i + 1, $params[$i]);


		if ($resource->execute()) {
			return true;
		} else {
			return false;
		}
	}

// </editor-fold>
	//
	//
	// <editor-fold defaultstate="collapsed" desc="Private Indexer functions">

	private static function index_generate($array, $file) {
		$code = "";

		$code = self::index_array_to_code($array);

		$code = "<?php return " . $code . ";";

		$path = dirname($file);
		if (!file_exists($path)) {
			mkdir($path, 0777, true);
		}

		$handle = fopen($file, 'w');
		fwrite($handle, $code);
		fclose($handle);
	}

	private static function code_generate($code, $file) {

		$path = dirname($file);
		if (!file_exists($path)) {
			mkdir($path, 0777, true);
		}

		$handle = fopen($file, 'w');
		fwrite($handle, $code);
		fclose($handle);
	}

	private static function index_array_to_code($array) {
		$code = "[";
		foreach ($array as $key => $value) {
			if (is_array($value))
				$code .= "'$key'=>" . self::index_array_to_code($value) . ",";
			else
				$code .= "'$key'=>'$value',";
		}
		$code = substr($code, 0, -1);
		$code .= "]";

		return $code;
	}

	private static function index_exists($file) {
		return (file_exists(realpath($file)));
	}

	private static function index_get($file) {
		$index = (include(realpath($file)));
		return $index;
	}

// </editor-fold>

	public function get_table_info() {
		$entityname = EntityInflector::camelize($this->table_name);
		$file = self::$setup['cache_path'] . '/TableInfo' . $entityname . '.php';
		$table_info = self::index_get($file);
		return $table_info;
	}

	private function hook_execute($pEvent, $pParams) {
		if (isset(self::$setup['events_hook'])) {
			$call = self::$setup['events_hook'];
			if (is_callable($call))
				$call("$this->table_name.$pEvent", $pParams);
		}
	}

	public function open($pID) { //returns a model or false;
		$this->hook_execute('opening', ['pID' => $pID]);

		$data = $this->getByID($pID);

		if ($data) {
			$this->hook_execute('opened', ['pID' => $pID, 'pModel' => $data]);
		}

		return $data;
	}

	public function create($pForcedID = 0, $pOverride = false) { //returns a blank model with default values
		$model = new $this->model_class;
		$model->forcedID = $pForcedID;
		$model->override = $pOverride;
		return $model;
	}

	public function save(&$model) {
		$cn = self::$sql;
		$keycolumn = $this->key_column;
		$table = $this->table_name;


		$this->hook_execute('saving', ['pModel' => $model]);

		if ($model->$keycolumn == 0) { //new record has to be saved.
			$this->hook_execute('creating', ['pModel' => $model]);
			if ($model->forcedID > 0 && !$this->find("$keycolumn = $model->forcedID")) //if there is a forced id supplied and was not found in the table then give the forced id to the keycoulmn
				$model->$keycolumn = $model->forcedID;
			else if ($model->forcedID > 0 && $this->find("$keycolumn=$model->forcedID"))   //if there is a forced id supplied and was found in the table then dont save the record and return false
				return false;
			else if ($model->forcedID == 0)  //if there was no forced id supplied then take new id from autonumber for the keycoulmn
				$model->$keycolumn = self::newid($table, $keycolumn);

			$values = array_values($model->data());

			///print_pre("Saving $this->table Values (" . implode($values,',') . ")");

			if (!self::execute($this->insert_sql, $values)) {
				throw new Exception("Saving $this->table Values ('" . implode($values, '', '') . "')");
			}

			$this->hook_execute('created', ['pModel' => $model]);
		} else { //exisisting record to be updated
			$this->hook_execute('updating', ['pModel' => $model]);

			$values = array_values($model->data());
			$values[] = $model->$keycolumn;

			//print_pre("Updating $this->table Values (" . implode($values,',') . ")");

			if (!self::execute($this->update_sql, $values)) {
				throw new Exception(print_pre(["Saving $this->table", $values], true));
			}

			$this->hook_execute('updated', ['pModel' => $model]);
		}

		$this->hook_execute('saved', ['pModel' => $model]);

		return $model;
	}

	public function delete($pWhere = '1=0') { //bydefault never delete any rows
		//print_pre("delete from $this->table where $pWhere");
		self::$sql->execute("delete from $this->table_name where $pWhere");
	}

	private static function table_exists($pTable) {
		return in_array($pTable, self::get_tables());
	}

	private static function newid($pTable, $pKeyColumn) {
		$sql = "";

		self::execute("CREATE TABLE IF NOT EXISTS AutoNumber (cTableName nvarchar(30) primary key, cNewID int)");
		self::execute("INSERT INTO AutoNumber (cTableName,cNewID) SELECT '$pTable',1 where not exists(select cTableName from Autonumber where cTableName = '$pTable')");
		$new_id = self::scalar("select cNewID from AutoNumber where cTableName='$pTable'");
		$max_id = 0;
		if (self::table_exists($pTable))
			$max_id = self::scalar("select MAX($pKeyColumn)+1 from $pTable");
		if ($max_id > $new_id)
			$new_id = $max_id;

		self::execute("UPDATE Autonumber SET cNewID=" . ($new_id + 1) . " where cTableName = '$pTable'");
		return $new_id;
	}

	public function find($pWhere = '1=1', $pReturnColumn = false) {
		if (!$pReturnColumn)
			$pReturnColumn = $this->key_column;

		$sql = self::$sql;

		$table = $this->table_name;

		$query = "select $pReturnColumn from $table where $pWhere";

		return self::scalar($query);
	}

	public function setAlert($pCode, $pMessage) {
		$this->alerts[] = [
			'code' => $pCode,
			'messasge' => $pMessage
		];
	}

	public function isAlert() {
		return count($this->alerts) > 0;
	}

	public function clearAlerts() {
		$this->alerts = [];
	}

	public function getAlerts() {
		return $this->alerts;
	}

}

class EntityInflector {

	/**
	 * Plural EntityInflector rules
	 *
	 * @var array
	 */
	protected static $_plural = [
		'/(s)tatus$/i' => '\1tatuses',
		'/(quiz)$/i' => '\1zes',
		'/^(ox)$/i' => '\1\2en',
		'/([m|l])ouse$/i' => '\1ice',
		'/(matr|vert|ind)(ix|ex)$/i' => '\1ices',
		'/(x|ch|ss|sh)$/i' => '\1es',
		'/([^aeiouy]|qu)y$/i' => '\1ies',
		'/(hive)$/i' => '\1s',
		'/(chef)$/i' => '\1s',
		'/(?:([^f])fe|([lre])f)$/i' => '\1\2ves',
		'/sis$/i' => 'ses',
		'/([ti])um$/i' => '\1a',
		'/(p)erson$/i' => '\1eople',
		'/(?<!u)(m)an$/i' => '\1en',
		'/(c)hild$/i' => '\1hildren',
		'/(buffal|tomat)o$/i' => '\1\2oes',
		'/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin)us$/i' => '\1i',
		'/us$/i' => 'uses',
		'/(alias)$/i' => '\1es',
		'/(ax|cris|test)is$/i' => '\1es',
		'/s$/' => 's',
		'/^$/' => '',
		'/$/' => 's',
	];

	/**
	 * Singular inflector rules
	 *
	 * @var array
	 */
	protected static $_singular = [
		'/(s)tatuses$/i' => '\1\2tatus',
		'/^(.*)(menu)s$/i' => '\1\2',
		'/(quiz)zes$/i' => '\\1',
		'/(matr)ices$/i' => '\1ix',
		'/(vert|ind)ices$/i' => '\1ex',
		'/^(ox)en/i' => '\1',
		'/(alias)(es)*$/i' => '\1',
		'/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
		'/([ftw]ax)es/i' => '\1',
		'/(cris|ax|test)es$/i' => '\1is',
		'/(shoe)s$/i' => '\1',
		'/(o)es$/i' => '\1',
		'/ouses$/' => 'ouse',
		'/([^a])uses$/' => '\1us',
		'/([m|l])ice$/i' => '\1ouse',
		'/(x|ch|ss|sh)es$/i' => '\1',
		'/(m)ovies$/i' => '\1\2ovie',
		'/(s)eries$/i' => '\1\2eries',
		'/([^aeiouy]|qu)ies$/i' => '\1y',
		'/(tive)s$/i' => '\1',
		'/(hive)s$/i' => '\1',
		'/(drive)s$/i' => '\1',
		'/([le])ves$/i' => '\1f',
		'/([^rfoa])ves$/i' => '\1fe',
		'/(^analy)ses$/i' => '\1sis',
		'/(analy|diagno|^ba|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
		'/([ti])a$/i' => '\1um',
		'/(p)eople$/i' => '\1\2erson',
		'/(m)en$/i' => '\1an',
		'/(c)hildren$/i' => '\1\2hild',
		'/(n)ews$/i' => '\1\2ews',
		'/eaus$/' => 'eau',
		'/^(.*us)$/' => '\\1',
		'/s$/i' => ''
	];

	/**
	 * Irregular rules
	 *
	 * @var array
	 */
	protected static $_irregular = [
		'atlas' => 'atlases',
		'beef' => 'beefs',
		'brief' => 'briefs',
		'brother' => 'brothers',
		'cafe' => 'cafes',
		'child' => 'children',
		'cookie' => 'cookies',
		'corpus' => 'corpuses',
		'cow' => 'cows',
		'criterion' => 'criteria',
		'ganglion' => 'ganglions',
		'genie' => 'genies',
		'genus' => 'genera',
		'graffito' => 'graffiti',
		'hoof' => 'hoofs',
		'loaf' => 'loaves',
		'man' => 'men',
		'money' => 'monies',
		'mongoose' => 'mongooses',
		'move' => 'moves',
		'mythos' => 'mythoi',
		'niche' => 'niches',
		'numen' => 'numina',
		'occiput' => 'occiputs',
		'octopus' => 'octopuses',
		'opus' => 'opuses',
		'ox' => 'oxen',
		'penis' => 'penises',
		'person' => 'people',
		'sex' => 'sexes',
		'soliloquy' => 'soliloquies',
		'testis' => 'testes',
		'trilby' => 'trilbys',
		'turf' => 'turfs',
		'potato' => 'potatoes',
		'hero' => 'heroes',
		'tooth' => 'teeth',
		'goose' => 'geese',
		'foot' => 'feet',
		'foe' => 'foes',
		'sieve' => 'sieves'
	];

	/**
	 * Words that should not be inflected
	 *
	 * @var array
	 */
	protected static $_uninflected = [
		'.*[nrlm]ese', '.*data', '.*deer', '.*fish', '.*measles', '.*ois',
		'.*pox', '.*sheep', 'people', 'feedback', 'stadia', '.*?media',
		'chassis', 'clippers', 'debris', 'diabetes', 'equipment', 'gallows',
		'graffiti', 'headquarters', 'information', 'innings', 'news', 'nexus',
		'pokemon', 'proceedings', 'research', 'sea[- ]bass', 'series', 'species', 'weather'
	];

	/**
	 * Default map of accented and special characters to ASCII characters
	 *
	 * @var array
	 */
	protected static $_transliteration = [
		'ä' => 'ae',
		'æ' => 'ae',
		'ǽ' => 'ae',
		'ö' => 'oe',
		'œ' => 'oe',
		'ü' => 'ue',
		'Ä' => 'Ae',
		'Ü' => 'Ue',
		'Ö' => 'Oe',
		'À' => 'A',
		'Á' => 'A',
		'Â' => 'A',
		'Ã' => 'A',
		'Å' => 'A',
		'Ǻ' => 'A',
		'Ā' => 'A',
		'Ă' => 'A',
		'Ą' => 'A',
		'Ǎ' => 'A',
		'à' => 'a',
		'á' => 'a',
		'â' => 'a',
		'ã' => 'a',
		'å' => 'a',
		'ǻ' => 'a',
		'ā' => 'a',
		'ă' => 'a',
		'ą' => 'a',
		'ǎ' => 'a',
		'ª' => 'a',
		'Ç' => 'C',
		'Ć' => 'C',
		'Ĉ' => 'C',
		'Ċ' => 'C',
		'Č' => 'C',
		'ç' => 'c',
		'ć' => 'c',
		'ĉ' => 'c',
		'ċ' => 'c',
		'č' => 'c',
		'Ð' => 'D',
		'Ď' => 'D',
		'Đ' => 'D',
		'ð' => 'd',
		'ď' => 'd',
		'đ' => 'd',
		'È' => 'E',
		'É' => 'E',
		'Ê' => 'E',
		'Ë' => 'E',
		'Ē' => 'E',
		'Ĕ' => 'E',
		'Ė' => 'E',
		'Ę' => 'E',
		'Ě' => 'E',
		'è' => 'e',
		'é' => 'e',
		'ê' => 'e',
		'ë' => 'e',
		'ē' => 'e',
		'ĕ' => 'e',
		'ė' => 'e',
		'ę' => 'e',
		'ě' => 'e',
		'Ĝ' => 'G',
		'Ğ' => 'G',
		'Ġ' => 'G',
		'Ģ' => 'G',
		'Ґ' => 'G',
		'ĝ' => 'g',
		'ğ' => 'g',
		'ġ' => 'g',
		'ģ' => 'g',
		'ґ' => 'g',
		'Ĥ' => 'H',
		'Ħ' => 'H',
		'ĥ' => 'h',
		'ħ' => 'h',
		'І' => 'I',
		'Ì' => 'I',
		'Í' => 'I',
		'Î' => 'I',
		'Ї' => 'Yi',
		'Ï' => 'I',
		'Ĩ' => 'I',
		'Ī' => 'I',
		'Ĭ' => 'I',
		'Ǐ' => 'I',
		'Į' => 'I',
		'İ' => 'I',
		'і' => 'i',
		'ì' => 'i',
		'í' => 'i',
		'î' => 'i',
		'ï' => 'i',
		'ї' => 'yi',
		'ĩ' => 'i',
		'ī' => 'i',
		'ĭ' => 'i',
		'ǐ' => 'i',
		'į' => 'i',
		'ı' => 'i',
		'Ĵ' => 'J',
		'ĵ' => 'j',
		'Ķ' => 'K',
		'ķ' => 'k',
		'Ĺ' => 'L',
		'Ļ' => 'L',
		'Ľ' => 'L',
		'Ŀ' => 'L',
		'Ł' => 'L',
		'ĺ' => 'l',
		'ļ' => 'l',
		'ľ' => 'l',
		'ŀ' => 'l',
		'ł' => 'l',
		'Ñ' => 'N',
		'Ń' => 'N',
		'Ņ' => 'N',
		'Ň' => 'N',
		'ñ' => 'n',
		'ń' => 'n',
		'ņ' => 'n',
		'ň' => 'n',
		'ŉ' => 'n',
		'Ò' => 'O',
		'Ó' => 'O',
		'Ô' => 'O',
		'Õ' => 'O',
		'Ō' => 'O',
		'Ŏ' => 'O',
		'Ǒ' => 'O',
		'Ő' => 'O',
		'Ơ' => 'O',
		'Ø' => 'O',
		'Ǿ' => 'O',
		'ò' => 'o',
		'ó' => 'o',
		'ô' => 'o',
		'õ' => 'o',
		'ō' => 'o',
		'ŏ' => 'o',
		'ǒ' => 'o',
		'ő' => 'o',
		'ơ' => 'o',
		'ø' => 'o',
		'ǿ' => 'o',
		'º' => 'o',
		'Ŕ' => 'R',
		'Ŗ' => 'R',
		'Ř' => 'R',
		'ŕ' => 'r',
		'ŗ' => 'r',
		'ř' => 'r',
		'Ś' => 'S',
		'Ŝ' => 'S',
		'Ş' => 'S',
		'Ș' => 'S',
		'Š' => 'S',
		'ẞ' => 'SS',
		'ś' => 's',
		'ŝ' => 's',
		'ş' => 's',
		'ș' => 's',
		'š' => 's',
		'ſ' => 's',
		'Ţ' => 'T',
		'Ț' => 'T',
		'Ť' => 'T',
		'Ŧ' => 'T',
		'ţ' => 't',
		'ț' => 't',
		'ť' => 't',
		'ŧ' => 't',
		'Ù' => 'U',
		'Ú' => 'U',
		'Û' => 'U',
		'Ũ' => 'U',
		'Ū' => 'U',
		'Ŭ' => 'U',
		'Ů' => 'U',
		'Ű' => 'U',
		'Ų' => 'U',
		'Ư' => 'U',
		'Ǔ' => 'U',
		'Ǖ' => 'U',
		'Ǘ' => 'U',
		'Ǚ' => 'U',
		'Ǜ' => 'U',
		'ù' => 'u',
		'ú' => 'u',
		'û' => 'u',
		'ũ' => 'u',
		'ū' => 'u',
		'ŭ' => 'u',
		'ů' => 'u',
		'ű' => 'u',
		'ų' => 'u',
		'ư' => 'u',
		'ǔ' => 'u',
		'ǖ' => 'u',
		'ǘ' => 'u',
		'ǚ' => 'u',
		'ǜ' => 'u',
		'Ý' => 'Y',
		'Ÿ' => 'Y',
		'Ŷ' => 'Y',
		'ý' => 'y',
		'ÿ' => 'y',
		'ŷ' => 'y',
		'Ŵ' => 'W',
		'ŵ' => 'w',
		'Ź' => 'Z',
		'Ż' => 'Z',
		'Ž' => 'Z',
		'ź' => 'z',
		'ż' => 'z',
		'ž' => 'z',
		'Æ' => 'AE',
		'Ǽ' => 'AE',
		'ß' => 'ss',
		'Ĳ' => 'IJ',
		'ĳ' => 'ij',
		'Œ' => 'OE',
		'ƒ' => 'f',
		'Þ' => 'TH',
		'þ' => 'th',
		'Є' => 'Ye',
		'є' => 'ye',
	];

	/**
	 * Method cache array.
	 *
	 * @var array
	 */
	protected static $_cache = [];

	/**
	 * The initial state of Inflector so reset() works.
	 *
	 * @var array
	 */
	protected static $_initialState = [];

	/**
	 * Cache inflected values, and return if already available
	 *
	 * @param string $type Inflection type
	 * @param string $key Original value
	 * @param string|bool $value Inflected value
	 * @return string|bool Inflected value on cache hit or false on cache miss.
	 */
	protected static function _cache($type, $key, $value = false) {
		$key = '_' . $key;
		$type = '_' . $type;
		if ($value !== false) {
			static::$_cache[$type][$key] = $value;

			return $value;
		}
		if (!isset(static::$_cache[$type][$key])) {
			return false;
		}

		return static::$_cache[$type][$key];
	}

	/**
	 * Clears Inflectors inflected value caches. And resets the inflection
	 * rules to the initial values.
	 *
	 * @return void
	 */
	public static function reset() {
		if (empty(static::$_initialState)) {
			static::$_initialState = get_class_vars(__CLASS__);

			return;
		}
		foreach (static::$_initialState as $key => $val) {
			if ($key !== '_initialState') {
				static::${$key} = $val;
			}
		}
	}

	/**
	 * Adds custom inflection $rules, of either 'plural', 'singular',
	 * 'uninflected', 'irregular' or 'transliteration' $type.
	 *
	 * ### Usage:
	 *
	 * ```
	 * Inflector::rules('plural', ['/^(inflect)or$/i' => '\1ables']);
	 * Inflector::rules('irregular', ['red' => 'redlings']);
	 * Inflector::rules('uninflected', ['dontinflectme']);
	 * Inflector::rules('transliteration', ['/å/' => 'aa']);
	 * ```
	 *
	 * @param string $type The type of inflection, either 'plural', 'singular',
	 *   'uninflected' or 'transliteration'.
	 * @param array $rules Array of rules to be added.
	 * @param bool $reset If true, will unset default inflections for all
	 *        new rules that are being defined in $rules.
	 * @return void
	 */
	public static function rules($type, $rules, $reset = false) {
		$var = '_' . $type;

		if ($reset) {
			static::${$var} = $rules;
		} elseif ($type === 'uninflected') {
			static::$_uninflected = array_merge(
					$rules, static::$_uninflected
			);
		} else {
			static::${$var} = $rules + static::${$var};
		}

		static::$_cache = [];
	}

	/**
	 * Return $word in plural form.
	 *
	 * @param string $word Word in singular
	 * @return string Word in plural
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-plural-singular-forms
	 */
	public static function pluralize($word) {
		if (isset(static::$_cache['pluralize'][$word])) {
			return static::$_cache['pluralize'][$word];
		}

		if (!isset(static::$_cache['irregular']['pluralize'])) {
			static::$_cache['irregular']['pluralize'] = '(?:' . implode('|', array_keys(static::$_irregular)) . ')';
		}

		if (preg_match('/(.*?(?:\\b|_))(' . static::$_cache['irregular']['pluralize'] . ')$/i', $word, $regs)) {
			static::$_cache['pluralize'][$word] = $regs[1] . substr($regs[2], 0, 1) .
					substr(static::$_irregular[strtolower($regs[2])], 1);

			return static::$_cache['pluralize'][$word];
		}

		if (!isset(static::$_cache['uninflected'])) {
			static::$_cache['uninflected'] = '(?:' . implode('|', static::$_uninflected) . ')';
		}

		if (preg_match('/^(' . static::$_cache['uninflected'] . ')$/i', $word, $regs)) {
			static::$_cache['pluralize'][$word] = $word;

			return $word;
		}

		foreach (static::$_plural as $rule => $replacement) {
			if (preg_match($rule, $word)) {
				static::$_cache['pluralize'][$word] = preg_replace($rule, $replacement, $word);

				return static::$_cache['pluralize'][$word];
			}
		}
	}

	/**
	 * Return $word in singular form.
	 *
	 * @param string $word Word in plural
	 * @return string Word in singular
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-plural-singular-forms
	 */
	public static function singularize($word) {
		if (isset(static::$_cache['singularize'][$word])) {
			return static::$_cache['singularize'][$word];
		}

		if (!isset(static::$_cache['irregular']['singular'])) {
			static::$_cache['irregular']['singular'] = '(?:' . implode('|', static::$_irregular) . ')';
		}

		if (preg_match('/(.*?(?:\\b|_))(' . static::$_cache['irregular']['singular'] . ')$/i', $word, $regs)) {
			static::$_cache['singularize'][$word] = $regs[1] . substr($regs[2], 0, 1) .
					substr(array_search(strtolower($regs[2]), static::$_irregular), 1);

			return static::$_cache['singularize'][$word];
		}

		if (!isset(static::$_cache['uninflected'])) {
			static::$_cache['uninflected'] = '(?:' . implode('|', static::$_uninflected) . ')';
		}

		if (preg_match('/^(' . static::$_cache['uninflected'] . ')$/i', $word, $regs)) {
			static::$_cache['pluralize'][$word] = $word;

			return $word;
		}

		foreach (static::$_singular as $rule => $replacement) {
			if (preg_match($rule, $word)) {
				static::$_cache['singularize'][$word] = preg_replace($rule, $replacement, $word);

				return static::$_cache['singularize'][$word];
			}
		}
		static::$_cache['singularize'][$word] = $word;

		return $word;
	}

	/**
	 * Returns the input lower_case_delimited_string as a CamelCasedString.
	 *
	 * @param string $string String to camelize
	 * @param string $delimiter the delimiter in the input string
	 * @return string CamelizedStringLikeThis.
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-camelcase-and-under-scored-forms
	 */
	public static function camelize($string, $delimiter = '_') {
		$cacheKey = __FUNCTION__ . $delimiter;

		$result = static::_cache($cacheKey, $string);

		if ($result === false) {
			$result = str_replace(' ', '', static::humanize($string, $delimiter));
			static::_cache($cacheKey, $string, $result);
		}

		return $result;
	}

	/**
	 * Returns the input CamelCasedString as an underscored_string.
	 *
	 * Also replaces dashes with underscores
	 *
	 * @param string $string CamelCasedString to be "underscorized"
	 * @return string underscore_version of the input string
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-camelcase-and-under-scored-forms
	 */
	public static function underscore($string) {
		return static::delimit(str_replace('-', '_', $string), '_');
	}

	/**
	 * Returns the input CamelCasedString as an dashed-string.
	 *
	 * Also replaces underscores with dashes
	 *
	 * @param string $string The string to dasherize.
	 * @return string Dashed version of the input string
	 */
	public static function dasherize($string) {
		return static::delimit(str_replace('_', '-', $string), '-');
	}

	/**
	 * Returns the input lower_case_delimited_string as 'A Human Readable String'.
	 * (Underscores are replaced by spaces and capitalized following words.)
	 *
	 * @param string $string String to be humanized
	 * @param string $delimiter the character to replace with a space
	 * @return string Human-readable string
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-human-readable-forms
	 */
	public static function humanize($string, $delimiter = '_') {
		$cacheKey = __FUNCTION__ . $delimiter;

		$result = static::_cache($cacheKey, $string);

		if ($result === false) {
			$result = explode(' ', str_replace($delimiter, ' ', $string));
			foreach ($result as &$word) {
				$word = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
			}
			$result = implode(' ', $result);
			static::_cache($cacheKey, $string, $result);
		}

		return $result;
	}

	/**
	 * Expects a CamelCasedInputString, and produces a lower_case_delimited_string
	 *
	 * @param string $string String to delimit
	 * @param string $delimiter the character to use as a delimiter
	 * @return string delimited string
	 */
	public static function delimit($string, $delimiter = '_') {
		$cacheKey = __FUNCTION__ . $delimiter;

		$result = static::_cache($cacheKey, $string);

		if ($result === false) {
			$result = mb_strtolower(preg_replace('/(?<=\\w)([A-Z])/', $delimiter . '\\1', $string));
			static::_cache($cacheKey, $string, $result);
		}

		return $result;
	}

	/**
	 * Returns corresponding table name for given model $className. ("people" for the model class "Person").
	 *
	 * @param string $className Name of class to get database table name for
	 * @return string Name of the database table for given class
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-table-and-class-name-forms
	 */
	public static function tableize($className) {
		$result = static::_cache(__FUNCTION__, $className);

		if ($result === false) {
			$result = static::pluralize(static::underscore($className));
			static::_cache(__FUNCTION__, $className, $result);
		}

		return $result;
	}

	/**
	 * Returns Cake model class name ("Person" for the database table "people".) for given database table.
	 *
	 * @param string $tableName Name of database table to get class name for
	 * @return string Class name
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-table-and-class-name-forms
	 */
	public static function classify($tableName) {
		$result = static::_cache(__FUNCTION__, $tableName);

		if ($result === false) {
			$result = static::camelize(static::singularize($tableName));
			static::_cache(__FUNCTION__, $tableName, $result);
		}

		return $result;
	}

	/**
	 * Returns camelBacked version of an underscored string.
	 *
	 * @param string $string String to convert.
	 * @return string in variable form
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-variable-names
	 */
	public static function variable($string) {
		$result = static::_cache(__FUNCTION__, $string);

		if ($result === false) {
			$camelized = static::camelize(static::underscore($string));
			$replace = strtolower(substr($camelized, 0, 1));
			$result = $replace . substr($camelized, 1);
			static::_cache(__FUNCTION__, $string, $result);
		}

		return $result;
	}

	/**
	 * Returns a string with all spaces converted to dashes (by default), accented
	 * characters converted to non-accented characters, and non word characters removed.
	 *
	 * @deprecated 3.2.7 Use Text::slug() instead.
	 * @param string $string the string you want to slug
	 * @param string $replacement will replace keys in map
	 * @return string
	 * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-url-safe-strings
	 */
	public static function slug($string, $replacement = '-') {
		$quotedReplacement = preg_quote($replacement, '/');

		$map = [
			'/[^\s\p{Zs}\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]/mu' => ' ',
			'/[\s\p{Zs}]+/mu' => $replacement,
			sprintf('/^[%s]+|[%s]+$/', $quotedReplacement, $quotedReplacement) => '',
		];

		$string = str_replace(
				array_keys(static::$_transliteration), array_values(static::$_transliteration), $string
		);

		return preg_replace(array_keys($map), array_values($map), $string);
	}

}
