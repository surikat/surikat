<?php
namespace RedBase\DataTable;
use RedBase\Exception;
class Sqlite extends SQL{
	protected $fullTextSearchLocale;
	function fullTextSearch($text,$tokensNumber=30,$targetColumnIndex=-1,
		$start='<b>',$end='</b>',$sep='<b>...</b>',$columns=[]
	){
		if($tokensNumber>64)
			$tokensNumber = 64;
		$sufx = $this->dataSource->getFtsTableSuffix();
		$ftsTable = $this->dataSource->escTable($this->name.$sufx);
		$table = $this->dataSource->escTable($this->name);
		$pk = $this->dataSource->esc($this->primaryKey);
		$this->dataSource->makeFtsTable($this->name,$columns,$this->primaryKey,$this->uniqTextKey,$this->fullTextSearchLocale);
		$this->select("snippet($ftsTable,?,?,?,?,?) as _snippet",
			[$start,$end,$sep,(int)$targetColumnIndex,(int)$tokensNumber]);
		$this->select("docid as $pk");
		$this->select("$table.*");
		$this->join("$ftsTable ON $table.$pk=$ftsTable.rowid");
		$this->unFrom($table);
		$limit = $this->getLimit();
		$offset = $this->getOffset();
		if($limit)
			$limit = 'LIMIT '.$limit;
		if($offset)
			$offset = 'OFFSET '.$offset;
		$this->join("(
			SELECT docid as $pk, matchinfo($ftsTable) AS rank
				FROM $ftsTable 
				WHERE $ftsTable MATCH ?
				ORDER BY rank DESC
				$limit $offset
		) AS _ranktable USING($pk)",[$text]);
		$this->where($ftsTable.' MATCH ?',[$text]);
		$this->orderBy('_ranktable.rank DESC');
		$this->setCounter(function()use($ftsTable,$text){
			if(!$this->exists())
				return;
			return (int)$this->dataSource->getCell("SELECT COUNT(*) FROM $ftsTable WHERE $ftsTable MATCH ?",[$text]);
		});
	}
}