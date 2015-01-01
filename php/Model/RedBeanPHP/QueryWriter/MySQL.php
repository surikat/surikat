<?php

namespace Surikat\Model\RedBeanPHP\QueryWriter;

use Surikat\Model\RedBeanPHP\QueryWriter\AQueryWriter as AQueryWriter;
use Surikat\Model\RedBeanPHP\QueryWriter as QueryWriter;
use Surikat\Model\RedBeanPHP\Adapter\DBAdapter as DBAdapter;
use Surikat\Model\RedBeanPHP\Adapter as Adapter;
use Surikat\Model\RedBeanPHP\Database;

/**
 * RedBean MySQLWriter
 *
 * @file    RedBean/QueryWriter/MySQL.php
 * @desc    Represents a MySQL Database to RedBean
 * @author  Gabor de Mooij and the RedBeanPHP Community
 * @license BSD/GPLv2
 *
 * (c) G.J.G.T. (Gabor) de Mooij and the RedBeanPHP Community.
 * This source file is subject to the BSD/GPLv2 License that is bundled
 * with this source code in the file license.txt.
 */
class MySQL extends AQueryWriter implements QueryWriter
{
	protected $separator = 'SEPARATOR';
	protected $agg = 'GROUP_CONCAT';
	protected $aggCaster = '';
	protected $sumCaster = '';
	protected $concatenator = '0x1D';
	
	/**
	 * Data types
	 */
	const C_DATATYPE_BOOL             = 0;
	const C_DATATYPE_UINT32           = 2;
	const C_DATATYPE_DOUBLE           = 3;
	const C_DATATYPE_TEXT8            = 4;
	const C_DATATYPE_TEXT16           = 5;
	const C_DATATYPE_TEXT32           = 6;
	const C_DATATYPE_TEXTUTF8         = 7;
	const C_DATATYPE_SPECIAL_DATE     = 80;
	const C_DATATYPE_SPECIAL_DATETIME = 81;
	const C_DATATYPE_SPECIAL_POINT    = 90;
	const C_DATATYPE_SPECIAL_LINESTRING = 91;
	const C_DATATYPE_SPECIAL_POLYGON    = 92;

	const C_DATATYPE_SPECIFIED        = 99;
	
	/**
	 * @var string
	 */
	protected $quoteCharacter = '`';

	/**
	 * Add the constraints for a specific database driver: MySQL.
	 *
	 * @param string $table     table     table to add constrains to
	 * @param string $table1    table1    first reference table
	 * @param string $table2    table2    second reference table
	 * @param string $property1 property1 first column
	 * @param string $property2 property2 second column
	 *
	 * @return boolean $succes whether the constraint has been applied
	 */
	protected function constrain( $table, $table1, $table2, $property1, $property2 )
	{
		try {
			$db  = $this->adapter->getCell( 'SELECT database()' );

			$fks = $this->adapter->getCell(
				"SELECT count(*)
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND
				CONSTRAINT_NAME <>'PRIMARY' AND REFERENCED_TABLE_NAME IS NOT NULL",
				[ $db, $table ]
			);

			// already foreign keys added in this association table
			if ( $fks > 0 ) {
				return FALSE;
			}

			$columns = $this->getColumns( $table );

			$idType = $this->getTypeForID();
			if ( $this->code( $columns[$property1] ) !== $idType ) {
				$this->widenColumn( $table, $property1, $idType );
			}

			if ( $this->code( $columns[$property2] ) !== $idType ) {
				$this->widenColumn( $table, $property2, $idType );
			}

			$sql = "
				ALTER TABLE " . $this->safeTable( $table ) . "
				ADD FOREIGN KEY($property1) references `$table1`(id) ON DELETE CASCADE ON UPDATE CASCADE;
			";

			$this->adapter->exec( $sql );

			$sql = "
				ALTER TABLE " . $this->safeTable( $table ) . "
				ADD FOREIGN KEY($property2) references `$table2`(id) ON DELETE CASCADE ON UPDATE CASCADE
			";

			$this->adapter->exec( $sql );

			return TRUE;
		} catch ( \Exception $e ) {
			return FALSE;
		}
	}

	/**
	 * Constructor
	 *
	 * @param Adapter $adapter Database Adapter
	 */
	public function __construct( Adapter $a, Database $db, $prefix='', $case=true )
	{
		parent::__construct($a,$db,$prefix,$case);
		$this->typeno_sqltype = [
			self::C_DATATYPE_BOOL             => 'TINYINT(1) UNSIGNED',
			self::C_DATATYPE_UINT32           => 'INT(11) UNSIGNED',
			self::C_DATATYPE_DOUBLE           => 'DOUBLE',
			self::C_DATATYPE_TEXT8            => 'VARCHAR(255)',
			self::C_DATATYPE_TEXT16           => 'TEXT',
			self::C_DATATYPE_TEXT32           => 'LONGTEXT',
			self::C_DATATYPE_TEXTUTF8         => 'VARCHAR(191)',
			self::C_DATATYPE_SPECIAL_DATE     => 'DATE',
			self::C_DATATYPE_SPECIAL_DATETIME => 'DATETIME',
			self::C_DATATYPE_SPECIAL_POINT    => 'POINT',
			self::C_DATATYPE_SPECIAL_LINESTRING => 'LINESTRING',
			self::C_DATATYPE_SPECIAL_POLYGON => 'POLYGON',
		];
		foreach ( $this->typeno_sqltype as $k => $v ) {
			$this->sqltype_typeno[trim( strtolower( $v ) )] = $k;
		}
		$this->encoding = $this->adapter->getDatabase()->getMysqlEncoding();
	}

	/**
	 * This method returns the datatype to be used for primary key IDS and
	 * foreign keys. Returns one if the data type constants.
	 *
	 * @return integer $const data type to be used for IDS.
	 */
	public function getTypeForID()
	{
		return self::C_DATATYPE_UINT32;
	}

	/**
	 * @see QueryWriter::getTables
	 */
	public function _getTables()
	{
		return $this->adapter->getCol( 'show tables' );
	}

	/**
	 * @see QueryWriter::createTable
	 */
	public function _createTable( $table )
	{
		$table = $this->safeTable( $table );

		$encoding = $this->adapter->getDatabase()->getMysqlEncoding();
		$sql   = "CREATE TABLE $table (id INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY ( id )) ENGINE = InnoDB DEFAULT CHARSET={$encoding} COLLATE={$encoding}_unicode_ci ";

		$this->adapter->exec( $sql );
	}

	/**
	 * @see QueryWriter::getColumns
	 */
	public function _getColumns( $table )
	{
		$columnsRaw = $this->adapter->get( "DESCRIBE " . $this->safeTable( $table ) );

		$columns = [];
		foreach ( $columnsRaw as $r ) {
			$columns[$r['Field']] = trim($r['Type']);
		}

		return $columns;
	}

	/**
	 * @see QueryWriter::scanType
	 */
	public function scanType( $value, $flagSpecial = FALSE )
	{
		$this->svalue = $value;

		if ( is_null( $value ) ) return self::C_DATATYPE_BOOL;
		if ( $value === INF ) return self::C_DATATYPE_TEXT8;

		if ( $flagSpecial ) {
			if ( preg_match( '/^\d{4}\-\d\d-\d\d$/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_DATE;
			}
			if ( preg_match( '/^\d{4}\-\d\d-\d\d\s\d\d:\d\d:\d\d$/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_DATETIME;
			}
			if ( preg_match( '/^POINT\(/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_POINT;
			}
			if ( preg_match( '/^LINESTRING\(/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_LINESTRING;
			}
			if ( preg_match( '/^POLYGON\(/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_POLYGON;
			}
		}

		//setter turns TRUE FALSE into 0 and 1 because database has no real bools (TRUE and FALSE only for test?).
		if ( $value === FALSE || $value === TRUE || $value === '0' || $value === '1' ) {
			return self::C_DATATYPE_BOOL;
		}

		if ( !$this->startsWithZeros( $value ) ) {

			if ( is_numeric( $value ) && ( floor( $value ) == $value ) && $value >= 0 && $value <= 4294967295 ) {
				return self::C_DATATYPE_UINT32;
			}

			if ( is_numeric( $value ) ) {
				return self::C_DATATYPE_DOUBLE;
			}
		}
		
		if ( mb_strlen( $value, 'UTF-8' ) <= 191 ) {
            return self::C_DATATYPE_TEXTUTF8;
        }
        
		if ( mb_strlen( $value, 'UTF-8' ) <= 255 ) {
			return self::C_DATATYPE_TEXT8;
		}

		if ( mb_strlen( $value, 'UTF-8' ) <= 65535 ) {
			return self::C_DATATYPE_TEXT16;
		}

		return self::C_DATATYPE_TEXT32;
	}

	/**
	 * @see QueryWriter::code
	 */
	public function code( $typedescription, $includeSpecials = FALSE )
	{
		if ( isset( $this->sqltype_typeno[$typedescription] ) ) {
			$r = $this->sqltype_typeno[$typedescription];
		} else {
			$r = self::C_DATATYPE_SPECIFIED;
		}

		if ( $includeSpecials ) {
			return $r;
		}

		if ( $r >= QueryWriter::C_DATATYPE_RANGE_SPECIAL ) {
			return self::C_DATATYPE_SPECIFIED;
		}

		return $r;
	}

	/**
	 * @see QueryWriter::addUniqueIndex
	 */
	public function addUniqueIndex( $table, $columns )
	{
		$table = $this->safeTable( $table );

		sort( $columns ); // Else we get multiple indexes due to order-effects

		foreach ( $columns as $k => $v ) {
			$columns[$k] = $this->safeColumn( $v );
		}

		$r    = $this->adapter->get( "SHOW INDEX FROM $table" );

		$name = 'UQ_' . sha1( implode( ',', $columns ) );

		if ( $r ) {
			foreach ( $r as $i ) {
				if ( $i['Key_name'] == $name ) {
					return;
				}
			}
		}

		try {
			$sql = "ALTER TABLE $table
						 ADD UNIQUE INDEX $name (" . implode( ',', $columns ) . ")";
		} catch ( \Exception $e ) {
			//do nothing, dont use alter table ignore, this will delete duplicate records in 3-ways!
		}

		$this->adapter->exec( $sql );
	}

	/**
	 * @see QueryWriter::addIndex
	 */
	public function addIndex( $type, $name, $column )
	{
		$table  = $type;
		$table  = $this->safeTable( $table );

		$name   = preg_replace( '/\W/', '', $name );

		$column = $this->safeColumn( $column );

		try {
			foreach ( $this->adapter->get( "SHOW INDEX FROM $table " ) as $ind ) if ( $ind['Key_name'] === $name ) return;
			$this->adapter->exec( "CREATE INDEX $name ON $table ($column) " );
		} catch (\Exception $e ) {
		}
	}

	/**
	 * @see QueryWriter::addFK
	 */
	public function addFK( $type, $targetType, $field, $targetField, $isDependent = FALSE )
	{

		$db = $this->adapter->getCell( 'SELECT DATABASE()' );

		$cfks = $this->adapter->getCell('
			SELECT CONSTRAINT_NAME
				FROM information_schema.KEY_COLUMN_USAGE
			WHERE
				TABLE_SCHEMA = ?
				AND TABLE_NAME = ?
				AND COLUMN_NAME = ? AND
				CONSTRAINT_NAME != \'PRIMARY\'
				AND REFERENCED_TABLE_NAME IS NOT NULL
		', [$db, $type, $field]);

		if ($cfks) return;

		try {
			$fkName = 'fk_'.($type.'_'.$field);
			$cName = 'c_'.$fkName;
			$this->adapter->exec( "
				ALTER TABLE  {$this->safeTable($type)}
				ADD CONSTRAINT $cName
				FOREIGN KEY $fkName ( {$this->safeColumn($field)} ) REFERENCES {$this->safeTable($targetType)} (
				{$this->safeColumn($targetField)}) ON DELETE " . ( $isDependent ? 'CASCADE' : 'SET NULL' ) . ' ON UPDATE '.( $isDependent ? 'CASCADE' : 'SET NULL' ).';');

		} catch (\Exception $e ) {
			// Failure of fk-constraints is not a problem
		}
	}

	/**
	 * @see QueryWriter::sqlStateIn
	 */
	public function sqlStateIn( $state, $list )
	{
		$stateMap = [
			'42S02' => QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
			'42S22' => QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
			'23000' => QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
		];

		return in_array( ( isset( $stateMap[$state] ) ? $stateMap[$state] : '0' ), $list );
	}

	/**
	 * @see QueryWriter::wipeAll
	 */
	public function _wipeAll()
	{
		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 0;' );

		foreach ( $this->getTables() as $t ) {
			try {
				$this->adapter->exec( "DROP TABLE IF EXISTS `$t`" );
			} catch (\Exception $e ) {
			}

			try {
				$this->adapter->exec( "DROP VIEW IF EXISTS `$t`" );
			} catch (\Exception $e ) {
			}
		}

		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 1;' );
	}

	public function _drop($t){
		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 0;' );
		try {
			$this->adapter->exec( "DROP TABLE IF EXISTS `$t`" );
		} catch (\Exception $e ) {
		}

		try {
			$this->adapter->exec( "DROP VIEW IF EXISTS `$t`" );
		} catch (\Exception $e ) {
		}
		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 1;' );
	}
	
}