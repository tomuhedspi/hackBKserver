<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CharResource extends JsonResource
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
            'word' => $this->word, 
            'reading' => $this->reading,
            'read' => $this->reading,
            'note' => $this->note, 
            'image' => $this->image, 
            'book' => $this->book, 
            'meaning' => $this->meaning,
            'type' => $this->type,
            'kun' => $this->kun,
            'on' => $this->on,
            'comment' => new CommentCollection($this->comments)
        ];
        return $data;
    }
}
