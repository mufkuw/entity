<?php

namespace Entities;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Instantiate the Entity Model pattern
 *
 * Following $pSetup array elements expected as parameter
 *
 * @param 'server' => ''
 * @param 'database' => ''
 * @param 'username' => ''
 * @param 'password' => ''
 * @param 'models_path' => 'models'
 * @param 'entities_path' => 'entities'
 * @param 'cache_path' => 'entities_classes'
 * @param 'events_hook' => null
 *
 *
 */
function entities_init($pSetup) {

	define('DEFAULT_INIT_ENTITY_PARAMS', [
		'server' => '',
		'database' => '',
		'username' => '',
		'password' => '',
		'models_path' => 'models',
		'entities_path' => 'entities',
		'cache_path' => 'entities_classes',
		'events_hook' => null
	]);

	$pSetup = array_merge(DEFAULT_INIT_ENTITY_PARAMS, $pSetup);

	$file_entity_base = $pSetup['cache_path'] . '/EntityBase.php';

	if (file_exists(realpath($file_entity_base))) {
		require_once $file_entity_base;
	}
	require_once './src/Entity.php';
	require_once './src/Model.php';

	Entity::init($pSetup);

	spl_autoload_register(function($class) {
		global $entities_class_index;
		if (isset($entities_class_index[$class])) {
			require_once($entities_class_index[$class]);
		}
	});
}
