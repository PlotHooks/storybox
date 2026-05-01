<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterConversationRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'conversation_id',
        'last_read_message_id',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Room::class, 'conversation_id');
    }

    public function lastReadMessage()
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}
