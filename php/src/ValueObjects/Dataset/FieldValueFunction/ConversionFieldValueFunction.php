<?php


namespace Kinintel\ValueObjects\Dataset\FieldValueFunction;


class ConversionFieldValueFunction extends FieldValueFunctionWithArguments {

    const supportedFunctions = [
        "toJSON"
    ];


    /**
     * Get supported function names
     *
     * @return string[]|void
     */
    protected function getSupportedFunctionNames() {
        return self::supportedFunctions;
    }

    /**
     * Apply a function with args
     *
     * @param $functionName
     * @param $functionArgs
     * @param $value
     * @param $dataItem
     * @return mixed|void
     */
    protected function applyFunctionWithArgs($functionName, $functionArgs, $value, $dataItem) {
        switch ($functionName) {
            case "toJSON":
                return $value ? json_encode($value) : $value;
        }
    }
}