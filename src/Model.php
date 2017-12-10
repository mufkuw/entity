<?php

namespace Entities;

abstract class Model {

	public $forcedID = 0;
	public $override = false;
	private $table_name = '';
	//private $row_data = array();
	private $extra_data = array();
	private $entity_class = '';

	public function data() {
		$array = (array) $this;
		foreach ($array as $key => $value) {
			if (substr($key, 0, 1) != 'c') {
				unset($array[$key]);
			}
		}

		$array = array_merge($array, $this->extra_data);


		return $array;
	}

	public function __construct($row_data = null) {

		if ($row_data) {
			foreach ($row_data as $column => $data) {
				$this->{$column} = $data;
			}
		}

		$class = get_called_class();
		$this->table_name = EntityInflector::pluralize(EntityInflector::delimit(substr($class, 0, -5)));
		$this->entity_class = EntityInflector::pluralize(substr($class, 0, -5)) . 'Entity';
	}

	public function __get($field_name) {
		if ($field_name == 'KeyID') {
			return (int) ($this->cID);
		}

		if (false) {
			return ($this->row_data[$field_name]);
		} else if (array_key_exists($field_name, $this->extra_data)) {
			return ($this->extra_data[$field_name]);
		} else {
			return null;
		}
	}

	public function __set($field_name, $value) {
		if (false) {
			$this->row_data[$field_name] = $value;
		} else {
			$this->extra_data[$field_name] = $value;
		}
	}

	public function __toString() {
		return '<pre>' . print_r($this->row_data, true) . '</pre>';
	}

	public function copy() {
		return clone $this;
	}

	public function merge($data) {
		if (is_array($data)) {
			foreach ($data as $column => $data) {
				$this->{$column} = $data;
			}
		} else if (is_a($data, 'Model')) {
			$row_data = $data->data();
			foreach ($row_data as $key => $value) {
				$this->$key = $value;
			}
		}
	}

	public function delete() {
		$class = $this->entity_class;
		if (class_exists($class)) {
			$instance = $class::instance();
			$instance->delete("cID=$this->KeyID");
		}
	}

	public function save() {
		$class = $this->entity_class;
		if (class_exists($class)) {
			$instance = $class::instance();
			$instance->save($this);
		}
	}

}
