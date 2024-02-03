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
        foreach ($this->queryString as $name) {
            if ($this->{$name}) {
                $array[$name] = $this->{$name};
            }
        }
        return $array;
    }
}