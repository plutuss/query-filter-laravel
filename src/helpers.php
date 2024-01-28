if (!function_exists('getDataFromComponent')) {

function getDataFromComponent(array $date)
{
    $result = collect($date)->map(function ($item) {
        return Arr::get($item, 'calls.0.params.1');
    });

    return $result->toArray();
}
}