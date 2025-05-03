<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Issue Entry
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Issue Entry </x-slot>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('issue_entries.store') }}" method="post" enctype="multipart/form-data">
        <div>
            @csrf
            @method('post')

            <button type="submit" class="btn btn-outline-info btn-sm"><i class="bi bi-save-fill"></i>Save</button>
        </div>
    </form>
    <div class="pb-3">
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
   


</x-backend.layouts.master>
