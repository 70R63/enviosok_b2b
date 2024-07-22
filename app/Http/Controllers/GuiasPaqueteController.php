<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuiasPaqueteRequest;
use App\Http\Requests\UpdateGuiasPaqueteRequest;
use App\Models\GuiasPaquete;

class GuiasPaqueteController extends Controller
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
     * @param  \App\Http\Requests\StoreGuiasPaqueteRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGuiasPaqueteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GuiasPaquete  $guiasPaquete
     * @return \Illuminate\Http\Response
     */
    public function show(GuiasPaquete $guiasPaquete)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\GuiasPaquete  $guiasPaquete
     * @return \Illuminate\Http\Response
     */
    public function edit(GuiasPaquete $guiasPaquete)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGuiasPaqueteRequest  $request
     * @param  \App\Models\GuiasPaquete  $guiasPaquete
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGuiasPaqueteRequest $request, GuiasPaquete $guiasPaquete)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GuiasPaquete  $guiasPaquete
     * @return \Illuminate\Http\Response
     */
    public function destroy(GuiasPaquete $guiasPaquete)
    {
        //
    }
}
