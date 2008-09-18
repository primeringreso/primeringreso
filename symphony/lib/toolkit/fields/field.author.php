<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	include_once(TOOLKIT . '/class.authormanager.php');
	
	Class fieldAuthor extends Field{
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Author';
		}

		function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}
		
		function getToggleStates(){
		    $authorManager = new AuthorManager($this->_engine);
		    $authors = $authorManager->fetch();
	
			$states = array();
			foreach($authors as $a) $states[$a->get('id')] = $a->get('first_name') . ' ' . $a->get('lastname');
			
			return $states;
		}

		function toggleFieldData($data, $newState){
			$data['author_id'] = $newState;
			return $data;
		}

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){	
			
			$status = self::__OK__;
			
			if(!is_array($data)) return array('author_id' => $data);
			
			if(empty($data)) return NULL;
			
			$result = array();
			foreach($data as $id) $result['author_id'][] = $id;

			return $result;
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			
			$value = (isset($data['author_id']) ? $data['author_id'] : NULL);			
			
			if(!is_array($value)) $value = array($value);
			
			if(!$value) $value = $this->_engine->getAuthorID();

		    $authorManager = new AuthorManager($this->_engine);
		    $authors = $authorManager->fetch();
		
			$options = array();

			foreach($authors as $a){
				$options[] = array($a->get('id'), in_array($a->get('id'), $value), $a->get('first_name') . ' ' . $a->get('lastname'));
			}
			
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';			
			
			$attr = array();
			
			if($this->get('allow_multiple_selection') == 'yes') $attr['multiple'] = 'multiple';
						
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, $attr));
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}
		
		function prepareTableValue($data, XMLElement $link=NULL){
			
			if(!is_array($data['author_id'])) $data['author_id'] = array($data['author_id']);
			
			if(empty($data['author_id'])) return NULL;
			
			$value = array();
			
			foreach($data['author_id'] as $author_id){
				$author = new Author($this->_engine);
				if($author->loadAuthor($author_id)) $value[] = $author->getFullName();
			}
			
			return parent::prepareTableValue(array('value' => General::sanitize(ucwords(implode(', ', $value)))), $link);
		}

		function isSortable(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		function canFilter(){
			return true;
		}
		
		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "`ed`.`author_id` $order");
		}
		
		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			$field_id = $this->get('id');
			
			if(self::isFilterRegex($data[0])):
				
				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.author_id REGEXP '$pattern' ";
						
			
			elseif($andOperation):
			
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.author_id = '$bit' ";
				}
							
			else:
			
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.author_id IN ('".@implode("', '", $data)."') ";
						
			endif;
			
			return true;
			
		}
		
		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
					
		}

		function appendFormattedElement(&$wrapper, $data, $encode=false){
			$author =& new Author($this->_engine, $data['author_id']);
			$wrapper->appendChild(new XMLElement($this->get('element_name'), $author->getFullName(), array('id' => $author->get('id'), 'username' => $author->get('username'))));
		}
			
		function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
		}
		
		function displaySettingsPanel(&$wrapper){
			
			parent::displaySettingsPanel($wrapper);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'related');

			## Allow multiple selection
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Allow selection of multiple authors');
			$div->appendChild($label);						
				
			$wrapper->appendChild($div);	
			
			$this->appendShowColumnCheckbox($wrapper);
					
		}
		
		function createTable(){
			return $this->_engine->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') ."` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `author_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `author_id` (`author_id`)
				) TYPE=MyISAM;"
				
			);
		}

		public function getExampleFormMarkup(){
			
		    $authorManager = new AuthorManager($this->_engine);
		    $authors = $authorManager->fetch();
		
			$options = array();

			foreach($authors as $a){
				$options[] = array($a->get('id'), NULL, $a->get('first_name') . ' ' . $a->get('lastname'));
			}
			
			$fieldname = 'fields['.$this->get('element_name').']';
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';			
			
			$attr = array();
			
			if($this->get('allow_multiple_selection') == 'yes') $attr['multiple'] = 'multiple';
						
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, $attr));
			
			return $label;
		}


	}

