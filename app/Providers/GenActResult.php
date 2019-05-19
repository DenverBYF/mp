<?php
/**
 * Created by PhpStorm.
 * User: baiyifan
 * Date: 2019-05-19
 * Time: 11:04
 */

namespace App\Providers;


class GenActResult
{
    protected $genType;


    public function __construct(GenActResultInterface $GenType)
    {
        $this->genType = $GenType;
    }

    public function handle($total, $number)
    {
        return $this->genType->getResult($total, $number);
    }

}