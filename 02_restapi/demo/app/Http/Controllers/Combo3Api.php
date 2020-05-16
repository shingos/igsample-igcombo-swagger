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

class Combo3Api extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Operation getFulltext
     *
     * サンプルデータ取得.
     *
     *
     * @return Http response
     */
    public function getFulltext()
    {
        $input = Request::all();
        $header = Request::header();
        $resHeaders = [
            'Content-Type' => 'text/json; charset=utf-8',
            'Access-Control-Allow-Origin' => array_key_exists('origin', $header) ? $header['origin'] : '*'
        ];

        $filter_text = null;
        foreach ($input as $key => $value) {
            if (strpos($key, '$filter(') === 0) {
                $filter_text = urldecode(
                    ($value[0]==='(') ? mb_substr($value, 1, mb_strlen($value) - 2) : $value
                );
                break;
            }
        }
        if (mb_strlen($filter_text) < 2) return response('[]', 200, $resHeaders);

        $query = DB::table('sample_ngram')->orderBy('id', 'asc')
            ->whereRaw('MATCH(`text_bigram`) AGAINST(? IN BOOLEAN MODE)', [$filter_text])
        ;

        return response()->stream(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, '[');
            $idx = 0;
            foreach (
                $query->select('id', 'title', 'author', 'text')
                ->cursor() as $record
            ) {
                if ($idx++ > 0) fwrite($stream, ',');
                fwrite($stream, json_encode([
                    "id"=>intval($record->id),
                    "title"=>$record->title,
                    "author"=>$record->author,
                    "text"=>$record->text
                ], JSON_UNESCAPED_UNICODE));
            }
            fwrite($stream, ']');
            fclose($stream);
        }, 200, $resHeaders);
    }
}