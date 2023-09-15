<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VideoLibrary;

class LibraryFile extends Model
{
    use HasFactory;
    protected $table = 'library_files';

    public function videoLibrary() {
        return $this->belongsTo(VideoLibrary::class);
    }
}
