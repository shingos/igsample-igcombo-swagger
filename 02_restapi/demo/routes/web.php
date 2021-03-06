<?php

/** @var \Laravel\Lumen\Routing\Router $router */
// lumen-server/lib/app/Http/routes.php

/**
 * igCombo Demo
 * igCombo デモ用 REST API
 *
 * OpenAPI spec version: 0.0.1
 * 
 *
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen.git
 * Do not edit the class manually.
 */

/**
 * igCombo Demo
 * @version 0.0.1
 */
$router->get('/', function () use ($router) {
    return $router->app->version();
});

/**
 * get getSamples
 * Summary: サンプルデータ取得
 * Notes: サンプルデータ一覧を取得します。

 */
$router->get('/api/samples', 'Combo1Api@getSamples');
/**
 * get getAuthors
 * Summary: 著者取得
 * Notes: 著者一覧を取得します。

 */
$router->get('/api/authors', 'Combo2Api@getAuthors');
/**
 * get getTitles
 * Summary: 作品名取得
 * Notes: 作品名一覧を取得します。

 */
$router->get('/api/{author}/titles', 'Combo2Api@getTitles');
/**
 * get getTextByTitle
 * Summary: 作品名取得
 * Notes: 作品名一覧を取得します。

 */
$router->get('/api/{author}/{title}/text', 'Combo2Api@getTextByTitle');
/**
 * get getSamples
 * Summary: サンプルデータ取得
 * Notes: サンプルデータ一覧を取得します。

 */
$router->get('/api/fulltext', 'Combo3Api@getFulltext');
