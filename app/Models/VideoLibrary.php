<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LibraryFile;

class VideoLibrary extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'video_libraries';

    public function libraryFile(){
        return $this->hasMany(LibraryFile::class, 'libraryId')->where('status', 1);
    }
}
