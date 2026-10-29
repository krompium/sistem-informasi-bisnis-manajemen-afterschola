<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * CRUD cabang (permission: manage branches). Tidak ada DELETE: cabang dinonaktifkan (MGT-04).
 */
class BranchController extends Controller
{
    public function index(Request $request)
    {
        // TODO(nanti): paginasi bila cabang membengkak — saat ini dipakai utuh untuk dropdown.
        return BranchResource::collection(Branch::visibleTo($request->user())->orderBy('name')->get());
    }

    public function store(SaveBranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return (new BranchResource($branch->refresh()))->response()->setStatusCode(201);
    }

    public function show(Branch $branch)
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(SaveBranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }
}
