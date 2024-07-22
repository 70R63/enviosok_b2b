<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storerastreo_peticionRequest;
use App\Http\Requests\Updaterastreo_peticionRequest;
use App\Models\rastreo_peticion;

class RastreoPeticionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\Storerastreo_peticionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Storerastreo_peticionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\rastreo_peticion  $rastreo_peticion
     * @return \Illuminate\Http\Response
     */
    public function show(rastreo_peticion $rastreo_peticion)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\rastreo_peticion  $rastreo_peticion
     * @return \Illuminate\Http\Response
     */
    public function edit(rastreo_peticion $rastreo_peticion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Updaterastreo_peticionRequest  $request
     * @param  \App\Models\rastreo_peticion  $rastreo_peticion
     * @return \Illuminate\Http\Response
     */
    public function update(Updaterastreo_peticionRequest $request, rastreo_peticion $rastreo_peticion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\rastreo_peticion  $rastreo_peticion
     * @return \Illuminate\Http\Response
     */
    public function destroy(rastreo_peticion $rastreo_peticion)
    {
        //
    }
}
