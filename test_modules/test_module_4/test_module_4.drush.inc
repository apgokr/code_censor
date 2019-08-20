<?php

/**
 * @file
 * Contains drush commands for test_module_4 module.
 */

/**
 * Implements hook_drush_command().
 */
function test_module_4_drush_command() {
  $commands = [];
  $commands['generate-unique-id'] = [
    'description' => 'Fixes missing uuid in migration source JSON files.',
    'arguments' => [],
    'options' => [],
    'drupal dependencies' => ['test_module_4', 'migrate_tools'],
    'aliases' => ['guid'],
  ];
  return $commands;
}

/**
 * Fixes missing uuid in migration source JSON files.
 */
function drush_test_module_4_generate_unique_id() {
  $helper = \Drupal::service('test_module_4.migrations_helper');
  $list = $helper->getAllFiles();
  foreach ($list as $path) {
    $helper->updateUidInSourceJson($path);
  }
  drush_log(dt("Updated JSON files with correct UIDs."), 'debug');
}
