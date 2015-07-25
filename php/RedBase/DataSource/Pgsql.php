<?php
namespace RedBase\DataSource;
class Pgsql extends SQL{
	const C_DATATYPE_INTEGER          = 0;
	const C_DATATYPE_BIGINT           = 1;
	const C_DATATYPE_DOUBLE           = 2;
	const C_DATATYPE_TEXT             = 3;
	const C_DATATYPE_SPECIAL_DATE     = 80;
	const C_DATATYPE_SPECIAL_DATETIME = 81;
	const C_DATATYPE_SPECIAL_POINT    = 90;
	const C_DATATYPE_SPECIAL_LSEG     = 91;
	const C_DATATYPE_SPECIAL_CIRCLE   = 92;
	const C_DATATYPE_SPECIAL_MONEY    = 93;
	const C_DATATYPE_SPECIAL_POLYGON  = 94;
	const C_DATATYPE_SPECIFIED        = 99;
	protected $defaultValue = 'DEFAULT';
	protected $quoteCharacter = '"';
	protected $version;
	function construct(array $config=[]){
		parent::construct($config);
		$this->typeno_sqltype = [
			self::C_DATATYPE_INTEGER          => ' integer ',
			self::C_DATATYPE_BIGINT           => ' bigint ',
			self::C_DATATYPE_DOUBLE           => ' double precision ',
			self::C_DATATYPE_TEXT             => ' text ',
			self::C_DATATYPE_SPECIAL_DATE     => ' date ',
			self::C_DATATYPE_SPECIAL_DATETIME => ' timestamp without time zone ',
			self::C_DATATYPE_SPECIAL_POINT    => ' point ',
			self::C_DATATYPE_SPECIAL_LSEG     => ' lseg ',
			self::C_DATATYPE_SPECIAL_CIRCLE   => ' circle ',
			self::C_DATATYPE_SPECIAL_MONEY    => ' money ',
			self::C_DATATYPE_SPECIAL_POLYGON  => ' polygon ',
		];
		$this->sqltype_typeno = [];
		foreach( $this->typeno_sqltype as $k => $v ){
			$this->sqltype_typeno[trim( strtolower( $v ) )] = $k;
		}
	}
	function connect(){
		if($this->isConnected)
			return;
		parent::connect();
		$serverVersion = $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
		$this->version = floatval($serverVersion);
	}
	function createDatabase($dbname){
		$this->pdo->exec('CREATE DATABASE "'.$dbname.'"');
	}
	protected function getInsertSuffix( $primaryKey ){
		return 'RETURNING "'.$primaryKey.'" ';
	}
	function getTablesQuery(){
		return $this->getCol( 'SELECT table_name FROM information_schema.tables WHERE table_schema = ANY( current_schemas( FALSE ) )' );
	}
	function scanType( $value, $flagSpecial = FALSE ){
		if ( $value === INF )
			return self::C_DATATYPE_TEXT;
		if ( $flagSpecial && $value ) {
			if ( preg_match( '/^\d{4}\-\d\d-\d\d$/', $value ) )
				return self::C_DATATYPE_SPECIAL_DATE;
			if ( preg_match( '/^\d{4}\-\d\d-\d\d\s\d\d:\d\d:\d\d(\.\d{1,6})?$/', $value ) )
				return self::C_DATATYPE_SPECIAL_DATETIME;
			if ( preg_match( '/^\([\d\.]+,[\d\.]+\)$/', $value ) )
				return self::C_DATATYPE_SPECIAL_POINT;
			if ( preg_match( '/^\[\([\d\.]+,[\d\.]+\),\([\d\.]+,[\d\.]+\)\]$/', $value ) )
				return self::C_DATATYPE_SPECIAL_LSEG;
			if ( preg_match( '/^\<\([\d\.]+,[\d\.]+\),[\d\.]+\>$/', $value ) )
				return self::C_DATATYPE_SPECIAL_CIRCLE;
			if ( preg_match( '/^\((\([\d\.]+,[\d\.]+\),?)+\)$/', $value ) )
				return self::C_DATATYPE_SPECIAL_POLYGON;
			if ( preg_match( '/^\-?(\$|€|¥|£)[\d,\.]+$/', $value ) )
				return self::C_DATATYPE_SPECIAL_MONEY;
		}
		if ( is_float( $value ) )
			return self::C_DATATYPE_DOUBLE;
		if ( self::startsWithZeros( $value ) )
			return self::C_DATATYPE_TEXT;
		if ( $value === FALSE || $value === TRUE || $value === NULL || ( is_numeric( $value )
				&& self::canBeTreatedAsInt( $value )
				&& $value <= 2147483647
				&& $value >= -2147483647 )
		)
			return self::C_DATATYPE_INTEGER;
		elseif ( is_numeric( $value )
				&& self::canBeTreatedAsInt( $value )
				&& $value <= 9223372036854775807
				&& $value >= -9223372036854775807 )
			return self::C_DATATYPE_BIGINT;
		elseif ( is_numeric( $value ) )
			return self::C_DATATYPE_DOUBLE;
		else
			return self::C_DATATYPE_TEXT;
	}
	function getColumnsQuery($table){
		$table = $this->prefixTable($table);
		$columnsRaw = $this->getAll("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='$table'");
		$columns = [];
		foreach ( $columnsRaw as $r ) {
			$columns[$r['column_name']] = $r['data_type'];
		}
		return $columns;
	}
	function createTableQuery($table,$pk='id'){
		$table = $this->escTable($table);
		$this->execute('CREATE TABLE '.$table.' ('.$pk.' SERIAL PRIMARY KEY)');
	}
	function addColumnQuery( $type, $column, $field ){
		$table  = $type;
		$type   = $field;
		$table  = $this->escTable( $table );
		$column = $this->esc( $column );
		$type = ( isset( $this->typeno_sqltype[$type] ) ) ? $this->typeno_sqltype[$type] : '';
		$this->execute('ALTER TABLE '.$table.' ADD '.$column.' '.$type);
	}
	function changeColumnQuery( $type, $column, $datatype ){
		$table   = $type;
		$type    = $datatype;
		$table   = $this->escTable( $table );
		$column  = $this->esc( $column );
		$newtype = $this->typeno_sqltype[$type];
		$this->execute('ALTER TABLE '.$table.' ALTER COLUMN '.$column.' TYPE '.$newtype);
	}
	
	function getKeyMapForType($type){
		$table = $this->prefixTable( $type );
		$keys = $this->getAll( '
			SELECT
			information_schema.key_column_usage.constraint_name AS "name",
			information_schema.key_column_usage.column_name AS "from",
			information_schema.constraint_table_usage.table_name AS "table",
			information_schema.constraint_column_usage.column_name AS "to",
			information_schema.referential_constraints.update_rule AS "on_update",
			information_schema.referential_constraints.delete_rule AS "on_delete"
				FROM information_schema.key_column_usage
			INNER JOIN information_schema.constraint_table_usage
				ON (
					information_schema.key_column_usage.constraint_name = information_schema.constraint_table_usage.constraint_name
					AND information_schema.key_column_usage.constraint_schema = information_schema.constraint_table_usage.constraint_schema
					AND information_schema.key_column_usage.constraint_catalog = information_schema.constraint_table_usage.constraint_catalog
				)
			INNER JOIN information_schema.constraint_column_usage
				ON (
					information_schema.key_column_usage.constraint_name = information_schema.constraint_column_usage.constraint_name
					AND information_schema.key_column_usage.constraint_schema = information_schema.constraint_column_usage.constraint_schema
					AND information_schema.key_column_usage.constraint_catalog = information_schema.constraint_column_usage.constraint_catalog
				)
			INNER JOIN information_schema.referential_constraints
				ON (
					information_schema.key_column_usage.constraint_name = information_schema.referential_constraints.constraint_name
					AND information_schema.key_column_usage.constraint_schema = information_schema.referential_constraints.constraint_schema
					AND information_schema.key_column_usage.constraint_catalog = information_schema.referential_constraints.constraint_catalog
				)
			WHERE
				information_schema.key_column_usage.table_catalog = current_database()
				AND information_schema.key_column_usage.table_schema = ANY( current_schemas( FALSE ) )
				AND information_schema.key_column_usage.table_name = ?
		', [$type] );
		$keyInfoList = [];
		foreach ( $keys as $k ) {
			$label = self::makeFKLabel( $k['from'], $k['table'], $k['to'] );
			$keyInfoList[$label] = array(
				'name'          => $k['name'],
				'from'          => $k['from'],
				'table'         => $k['table'],
				'to'            => $k['to'],
				'on_update'     => $k['on_update'],
				'on_delete'     => $k['on_delete']
			);
		}
		return $keyInfoList;
	}
	function addFK( $type, $targetType, $property, $targetProperty, $isDep = FALSE ){
		$table = $this->escTable( $type );
		$targetTable = $this->escTable( $targetType );
		$field = $this->esc( $property );
		$targetField = $this->esc( $targetProperty );
		$tableNoQ = $this->prefixTable( $type );
		$fieldNoQ = $this->check( $property );
		
		$casc = ( $isDep ? 'CASCADE' : 'SET NULL' );
		$fk = $this->getForeignKeyForTypeProperty( $type, $fieldNoQ );
		if ( !is_null( $fk )
			&&($fk['on_update']==$casc||$fk['on_update']=='CASCADE')
			&&($fk['on_delete']==$casc||$fk['on_update']=='CASCADE')
		)
			return false;
		try{
			$this->execute( "ALTER TABLE {$table}
				ADD FOREIGN KEY ( {$field} ) REFERENCES  {$targetTable}
				({$targetField}) ON DELETE {$casc} ON UPDATE {$casc} DEFERRABLE ;" );
			return true;
		} catch ( \PDOException $e ) {
			return false;
		}
	}
	function columnCode( $typedescription, $includeSpecials = FALSE ){
		$r = isset($this->sqltype_typeno[$typedescription])?$this->sqltype_typeno[$typedescription]:99;
		if ( $includeSpecials )
			return $r;
		if ( $r >= self::C_DATATYPE_RANGE_SPECIAL )
			return self::C_DATATYPE_SPECIFIED;
		return $r;
	}
	function getTypeForID(){
		return self::C_DATATYPE_INTEGER;
	}
	function addUniqueConstraint( $type, $properties ){
		$tableNoQ = $this->prefixTable( $type );
		$columns = [];
		foreach( (array)$properties as $key => $column )
			$columns[$key] = $this->esc( $column );
		$table = $this->escTable( $type );
		sort( $columns ); //else we get multiple indexes due to order-effects
		$name = "UQ_" . sha1( $table . implode( ',', $columns ) );
		$sql = "ALTER TABLE {$table}
                ADD CONSTRAINT $name UNIQUE (" . implode( ',', $columns ) . ")";
		try {
			$this->execute( $sql );
		}
		catch( \PDOException $e ) {
			return false;
		}
		return true;
	}
	function addIndex( $type, $name, $property ){
		$table  = $this->escTable( $type );
		$name   = preg_replace( '/\W/', '', $name );
		$column = $this->esc( $property );
		try{
			$this->execute( "CREATE INDEX {$name} ON $table ({$column}) " );
			return true;
		}
		catch(\PDOException $e){
			return false;
		}
	}
	
	function clear($type){
		$table = $this->escTable($type);
		$this->execute('TRUNCATE '.$table);
	}
	protected function _drop($type){
		$t = $this->escTable($type);
		$this->execute('SET CONSTRAINTS ALL DEFERRED');
		$this->execute("DROP TABLE IF EXISTS $t CASCADE ");
		$this->execute('SET CONSTRAINTS ALL IMMEDIATE');
	}
	protected function _dropAll(){
		$this->execute('SET CONSTRAINTS ALL DEFERRED');
		foreach($this->getTables() as $t){
			$this->execute('DROP TABLE IF EXISTS "'.$t.'" CASCADE ');
		}
		$this->execute('SET CONSTRAINTS ALL IMMEDIATE');
	}
	
	protected function explain($sql,$bindings=[]){
		$sql = ltrim($sql);
		if(!in_array(strtoupper(substr($sql,0,6)),['SELECT','DELETE','INSERT','UPDATE','VALUES'])
			&&!in_array(strtoupper(substr($sql,0,7)),['REPLACE','EXECUTE','DECLARE'])
		)
			return false;
		$explain = $this->pdo->prepare('EXPLAIN '.$sql,[\PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT=>true]);
		$this->bindParams($explain,$bindings);
		$explain->execute();
		$explain = $explain->fetchAll();
		return implode("\n",array_map(function($entry){
			return implode("\n",$entry);
		}, $explain));
	}
	
	function getFkMap($type,$primaryKey='id'){
		$table = $this->prefixTable($type);
		return $this->getAll('SELECT
			tc.table_name AS table,
			kcu.column_name as column,
			tc.constraint_name as constraint,
			update_rule as on_update,
			delete_rule as on_delete
		FROM
			information_schema.referential_constraints AS rc
		JOIN
			information_schema.table_constraints AS tc USING(constraint_catalog,constraint_schema,constraint_name)
		JOIN
			information_schema.key_column_usage AS kcu USING(constraint_catalog,constraint_schema,constraint_name)
		JOIN
			information_schema.key_column_usage AS ccu ON(ccu.constraint_catalog=rc.unique_constraint_catalog AND ccu.constraint_schema=rc.unique_constraint_schema AND ccu.constraint_name=rc.unique_constraint_name)
		WHERE
			ccu.table_catalog=current_database()
			AND ccu.table_schema=ANY( current_schemas( FALSE ) )
			AND ccu.table_name=?
			AND ccu.column_name=?',[$table,$primaryKey]);
	}
	
	function adaptPrimaryKey($type,$id,$primaryKey='id'){
		if($id!=2147483647)
			return;
		$cols = $this->getColumns($type);
		if($cols[$primaryKey]=='bigint')
			return;
		$table = $this->escTable($type);
		$pk = $this->esc($primaryKey);
		$fks = $this->getFkMap($type,$primaryKey);
		foreach($fks as $fk){
			$this->execute('ALTER TABLE "'.$fk['table'].'" ALTER "'.$fk['column'].'" TYPE bigint');
		}
		$this->execute('ALTER TABLE '.$table.' ALTER '.$pk.' TYPE bigint');
	}
	
	function autoFillTextColumns($type,$uniqTextKey){
		$sufxL = -1*strlen($this->ftsTableSuffix);
		$columns = [];
		foreach($this->getColumns($type) as $col=>$colType){
			if(($colType=='text'||substr($colType,0,4)=='date')
				&&($col==$uniqTextKey||substr($col,$sufxL)==$this->ftsTableSuffix))
				$columns[] = $col;
		}
		return $columns;
	}
	function addFtsColumn($type,&$columns=[],$primaryKey='id',$uniqTextKey='uniq',$lang=null){
		$columnsMap = $this->getColumns($type);
		$table = $this->prefixTable($type);
		if(empty($columns)){
			$columns = $this->autoFillTextColumns($type,$uniqTextKey);
			if(empty($columns))
				throw new Exception('Unable to find columns from "'.$table.'" to create FTS column "'.$col.'"');
			sort($columns);
			$indexName = '_auto_'.implode('_',$columns);
			$vacuum = false;
			foreach($columnsMap as $k=>$v){
				if(substr($k,6)=='_auto_'&&$type='tsvector'){
					$this->execute('ALTER TABLE "'.$table.'" DROP COLUMN `'.$indexName.'`');
					$vacuum = true;
				}
			}
			if($vacuum)
				$this->execute('VACUUM FULL "'.$table.'"');
		}
		else{
			sort($columns);
			$indexName = implode('_',$columns);
		}
		if(!isset($columnsMap[$indexName])){
			$this->execute('ALTER TABLE "'.$table.'" ADD "'.$indexName.'" tsvector');
			if(!isset($name))
				$name = $table.'_'.$indexName.'_fulltext';
			$name   = preg_replace('/\W/', '', $name);
			$this->execute('CREATE INDEX '.$name.' ON "'.$table.'" USING gin("'.$indexName.'")');
			if($lang)
				$this->execute('ALTER TABLE "'.$table.'" ADD language text NOT NULL DEFAULT(\''.$lang.'\')');
			$sql = $this->buildColumnFulltextSQL($table, $indexName, $columns, $primaryKey, $lang);
			$this->execute($sql);
		}
		return $indexName;
	}

	function buildColumnFulltextSQL($type, $col, $cols , $primaryKey='id', $lang=''){
		$table = $this->prefixTable($type);
		if($this->version>=9){
			$agg = 'string_agg';
			$sep = ',';
		}
		else{
			$agg = 'array_to_string(array_agg';
			$sep = '),';			
		}
		$cc = "' '";
		$id = $this->esc($primaryKey);
		$tb = $this->escTable($table);
		$_tb = $this->esc('_'.$table);
		$groupBy = [];
		$columns = [];
		$tablesJoin = [];
		if($lang)
			$lang = "'$lang',";
		foreach($cols as $select){
			$shareds = [];
			$typeParent = $table;
			$aliasParent = $table;
			$type = '';
			$l = strlen($select);
			$weight = '';
			$relation = null;
			$exist = true;
			for($i=0;$i<$l;$i++){
				switch($select[$i]){
					case '/':
						$i++;
						while(isset($select[$i])){
							$weight .= $select[$i];
							$i++;
						}
						$weight = trim($weight);
					break;
					case '.':
					case '>': //many
						list($type,$alias) = self::specialTypeAliasExtract($type,$superalias);
						if($superalias)
							$alias = $superalias.'__'.$alias;
						$joint = $type!=$alias?"\"{$this->tablePrefix}$type\" as \"$alias\"":'"'.$this->tablePrefix.$alias.'"';
						if($exist=($this->tableExists($type)&&$this->columnExists($type,$typeParent.'_'.$primaryKey)))
							$tablesJoin[] = "JOIN $joint ON \"{$this->tablePrefix}$aliasParent\".\"{$primaryKey}\"=\"{$this->tablePrefix}$alias\".\"{$typeParent}_{$primaryKey}\"";
						$typeParent = $type;
						$aliasParent = $alias;
						$type = '';
						$relation = '>';
					break;
					case '<':
						list($type,$alias) = self::specialTypeAliasExtract($type,$superalias);
						if(isset($select[$i+1])&&$select[$i+1]=='>'){ //many2many
							$i++;
							if($superalias)
								$alias = $superalias.'__'.($alias?$alias:$type);
							$rels = [$typeParent,$type];
							sort($rels);
							$imp = implode('_',$rels);
							$join[$imp][] = $alias;
							if($exist=($this->tableExists($type)&&$this->tableExists($imp))){
								$tablesJoin[] = "JOIN \"{$this->tablePrefix}$imp\" ON \"{$this->tablePrefix}$typeParent\".\"{$primaryKey}\"=\"{$this->tablePrefix}$imp\".\"{$typeParent}_{$primaryKey}\"";
								$joint = $type!=$alias?"\"{$this->tablePrefix}$type\" as \"$alias\"":'"'.$this->tablePrefix.$alias.'"';
								$tablesJoin[] = "JOIN $joint ON \"{$this->tablePrefix}$alias\".\"{$primaryKey}\"=\"{$this->tablePrefix}$imp\".\"{$type}".(in_array($type,$shareds)?2:'')."_{$primaryKey}\"";
							}
							$shareds[] = $type;
							$typeParent = $type;
							$relation = '<>';
						}
						else{ //one
							if($superalias)
								$alias = $superalias.'__'.$alias;
							$join[$type][] = ($alias?[$typeParent,$alias]:$typeParent);
							$joint = $type!=$alias?"\"{$this->tablePrefix}$type\" as \"$alias\"":'"'.$this->tablePrefix.$alias.'"';
							if($exist=($this->tableExists($typeParent)&&$this->columnExists($typeParent,$type.'_'.$primaryKey)))
								$tablesJoin[] = "JOIN $joint ON \"{$this->tablePrefix}$alias\".\"{$primaryKey}\"=\"{$this->tablePrefix}$typeParent\".\"{$type}_{$primaryKey}\"";
							$typeParent = $type;
							$relation = '<';
						}
						$type = '';
					break;
					default:
						$type .= $select[$i];
					break;
				}
			}
			if($this->tableExists($typeParent)){
				$localTable = $typeParent;
				$localCol = trim($type);
				switch($relation){
					default:
						$c = 'COALESCE("'.$this->tablePrefix.$localTable.'"."'.$localCol.'"'.",''::text)";
						$gb = '"'.$this->tablePrefix.$localTable.'"."'.$localCol.'"';
						if(!in_array($gb,$groupBy))
							$groupBy[] = $gb;
					break;
					case '<':
						$c = 'COALESCE("'.$this->tablePrefix.$localTable.'"."'.$localCol.'"'.",''::text)";
						$gb = '"'.$this->tablePrefix.$localTable.'"."'.$localCol.'"';
						if($this->columnExists($localTable,$localCol.'_'.$primaryKey)){
							if(!in_array($gb,$groupBy))
								$groupBy[] = $gb;
							$gb = '"'.$this->tablePrefix.$localTable.'"."'.$primaryKey.'"';
							if(!in_array($gb,$groupBy))
								$groupBy[] = $gb;
						}
					break;
					case '>':
						$c = "{$agg}(COALESCE(\"{$this->tablePrefix}{$localTable}\".\"{$localCol}\"::text,''::text) {$sep} {$cc})";
					break;
					case '<>':
						$c = "{$agg}(COALESCE(\"{$this->tablePrefix}{$localTable}\".\"{$localCol}\"::text,''::text) {$sep} {$cc})";
					break;
				}
				$c = "to_tsvector($lang$c)";
				if($weight)
					$c = "setweight($c,'$weight')";
				if($exist)
					$columns[] = $c;
			}
		}
		$sqlUpdate = 'UPDATE '.$tb.' as '.$_tb;
		$sqlUpdate .= ' SET '.$col.'=(SELECT '.implode("||",$columns);
		$sqlUpdate .= ' FROM '.$tb;
		$sqlUpdate .= implode(" \n",$tablesJoin);
		$sqlUpdate .= ' WHERE '.$tb.'.'.$id.'='.$_tb.'.'.$id;
		if(!empty($groupBy))
			$sqlUpdate .= ' GROUP BY '.implode(',',$groupBy);
		$sqlUpdate .= ')';
		return $sqlUpdate;
	}
	
	static function specialTypeAliasExtract($type,&$superalias=null){
		$alias = null;
		if(($p=strpos($type,':'))!==false){
			if(isset($type[$p+1])&&$type[$p+1]==':'){
				$superalias = trim(substr($type,$p+2));
				$type = trim(substr($type,0,$p));
			}
			else{
				$alias = trim(substr($type,$p+1));
				$type = trim(substr($type,0,$p));
			}
		}
		return [$type,$alias?$alias:$type];
	}
}