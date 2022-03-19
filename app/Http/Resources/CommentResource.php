<?php

namespace App\Http\Resources;

use App\Models\Interactive;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'author_name' => $this->author_name, 
            'content' => $this->content,
            'like' => $this->interactive->where('status', Interactive::LIKE)->count(),
            'unlike' => $this->interactive->where('status', Interactive::UNLIKE)->count(),
        ];
        return $data;
    }
}
