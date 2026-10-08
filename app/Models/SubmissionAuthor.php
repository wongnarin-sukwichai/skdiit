<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionAuthor extends Model
{
    public $timestamps = false;

    protected $fillable = ['position', 'name', 'affiliation', 'email'];
}
