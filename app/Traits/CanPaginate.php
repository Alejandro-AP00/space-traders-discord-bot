<?php

namespace App\Traits;

use AlejandroAPorras\SpaceTraders\Support\PaginatedResults;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait CanPaginate
{
    public function paginate(Message $message, PaginatedResults $paginator, ?string $emptyMessage, string $routeName, $callback): Message
    {
        if (count($paginator->results()) === 0) {
            return $message->color('#eb4034')
                ->content($emptyMessage);
        }

        $item = $paginator[0];
        $next_page = $paginator->nextPage()['page'] ?? null;
        $prev_page = $paginator->previousPage()['page'] ?? null;

        if ($paginator->totalPages() > 1) {
            $message
                ->button('Previous', disabled: ! $prev_page, id: Str::uuid(), route: "{$routeName}:{$prev_page}")
                ->button('Next', disabled: ! $next_page, id: Str::uuid(), route: "{$routeName}:{$next_page}")
                ->footerText('Page '.$paginator->currentPage().'/'.$paginator->totalPages());
        }

        return $callback($message, $item);
    }

    public function paginateFromArray(Message $message, array $results, ?string $emptyMessage, string $routeName, $callback, int $perPage = 1, int $page = 1): Message
    {
        $results = collect($results);
        if ($results->isEmpty()) {
            return $message->color('#eb4034')
                ->content($emptyMessage);
        }

        $items = $results->forPage($page, $perPage);
        $total_pages = ceil($results->count() / $perPage);
        $prev_page = $page === 1 ? null : $page - 1;
        $next_page = $results->forPage($page + 1, $perPage)->isNotEmpty() ? $page + 1 : null;

        if ($total_pages > 1) {
            $message
                ->button('Previous', disabled: ! $prev_page, id: Str::uuid(), route: "{$routeName}:{$prev_page}")
                ->button('Next', disabled: ! $next_page, id: Str::uuid(), route: "{$routeName}:{$next_page}")
                ->footerText('Page '.$page.'/'.$total_pages);
        }

        return $callback($message, $items);
    }
}
