<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/SQL.php';
require_once __DIR__ . '/core/ErrorManager.php';
require_once __DIR__ . '/core/Logger.php';


// foreach (SQL::dropAllTables() as $query) {
//     DB::execute($query);
// }


DB::execute(SQL::createUsersTable());
DB::execute(SQL::createTripsTable());
DB::execute(SQL::createMediaTable());