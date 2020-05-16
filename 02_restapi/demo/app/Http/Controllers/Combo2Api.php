<?php

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


namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Routing\ResponseFactory;

class Combo2Api extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Operation getAuthors
     *
     * 著者取得.
     *
     *
     * @return Http response
     */
    public function getAuthors()
    {
        $input = Request::all();
        $header = Request::header();

        $query = DB::table('sample')->distinct();

        return response()->stream(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, '[');
            $idx = 0;
            foreach (
                $query->select('author')
                ->cursor() as $record
            ) {
                if ($idx++ > 0) fwrite($stream, ',');
                fwrite($stream, json_encode([
                    "author"=>$record->author
                ], JSON_UNESCAPED_UNICODE));
            }
            fwrite($stream, ']');
            fclose($stream);
        }, 
        200,
        [
            'Content-Type' => 'text/json; charset=utf-8',
            'Access-Control-Allow-Origin' => array_key_exists('origin', $header) ? $header['origin'] : '*'
        ]);
    }
    /**
     * Operation getTitles
     *
     * 作品名取得.
     *
     * @param string $author  (required)
     *
     * @return Http response
     */
    public function getTitles($author)
    {
        $input = Request::all();
        $header = Request::header();

        $query = DB::table('sample')->distinct()
            ->where('author', urldecode($author))
        ;

        return response()->stream(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, '[');
            $idx = 0;
            foreach (
                $query->select('title')
                ->cursor() as $record
            ) {
                if ($idx++ > 0) fwrite($stream, ',');
                fwrite($stream, json_encode([
                    "title"=>$record->title
                ], JSON_UNESCAPED_UNICODE));
            }
            fwrite($stream, ']');
            fclose($stream);
        }, 
        200,
        [
            'Content-Type' => 'text/json; charset=utf-8',
            'Access-Control-Allow-Origin' => array_key_exists('origin', $header) ? $header['origin'] : '*'
        ]);
    }
    /**
     * Operation getTextByTitle
     *
     * サンプルデータ取得.
     *
     * @param string $title  (required)
     * @param string $author  (required)
     *
     * @return Http response
     */
    public function getTextByTitle($title, $author)
    {
        $input = Request::all();
        $header = Request::header();

        $query = DB::table('sample')
            ->where('author', urldecode($author))
            ->where('title', urldecode($title))
        ;

        return response()->stream(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, '[');
            $idx = 0;
            foreach (
                $query->select('id', 'text')
                ->cursor() as $record
            ) {
                if ($idx++ > 0) fwrite($stream, ',');
                fwrite($stream, json_encode([
                    "id"=>intval($record->id),
                    "text"=>$record->text
                ], JSON_UNESCAPED_UNICODE));
            }
            fwrite($stream, ']');
            fclose($stream);
        }, 
        200,
        [
            'Content-Type' => 'text/json; charset=utf-8',
            'Access-Control-Allow-Origin' => array_key_exists('origin', $header) ? $header['origin'] : '*'
        ]);
    }
}