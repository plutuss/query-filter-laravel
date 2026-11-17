<?php

namespace Plutuss\Traits\Livewire;

trait WithQueryParameters
{

    /**
     * @return array
     */
    private function getQueryParams(): array
    {
        $array = [];

        foreach ($this->queryString as $key => $options) {
            $name = is_int($key) ? $options : $key;
            $value = $this->{$name};

            if ($value !== null && $value !== '' && $value !== []) {
                $array[$name] = $value;
            }
        }
        return $array;
    }
}
