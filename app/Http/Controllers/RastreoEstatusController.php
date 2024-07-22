<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storerastreo_estatusRequest;
use App\Http\Requests\Updaterastreo_estatusRequest;
use App\Models\rastreo_estatus;

class RastreoEstatusController extends Controller
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
     * @param  \App\Http\Requests\Storerastreo_estatusRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Storerastreo_estatusRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\rastreo_estatus  $rastreo_estatus
     * @return \Illuminate\Http\Response
     */
    public function show(rastreo_estatus $rastreo_estatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\rastreo_estatus  $rastreo_estatus
     * @return \Illuminate\Http\Response
     */
    public function edit(rastreo_estatus $rastreo_estatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Updaterastreo_estatusRequest  $request
     * @param  \App\Models\rastreo_estatus  $rastreo_estatus
     * @return \Illuminate\Http\Response
     */
    public function update(Updaterastreo_estatusRequest $request, rastreo_estatus $rastreo_estatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\rastreo_estatus  $rastreo_estatus
     * @return \Illuminate\Http\Response
     */
    public function destroy(rastreo_estatus $rastreo_estatus)
    {
        //
    }
}
