<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ResultadoController extends Controller
{
    public function index()
    {
        return view('admin.resultados');
    }
}
