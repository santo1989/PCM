<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Dashboard
    </x-slot>
    {{-- <x-slot name='breadCrumb'>
                <x-backend.layouts.elements.breadcrumb>
                    <x-slot name="pageHeader"> Monthly Report Search </x-slot>
                    <li class="breadcrumb-item active">Dashboard</li>
                </x-backend.layouts.elements.breadcrumb>
            </x-slot> --}}

    <div class="container">

        {{-- <iframe title="MonthlyCost" width="99%" height="500px"
            src="https://app.powerbi.com/view?r=eyJrIjoiZmNiMDQyNTYtYTllOC00NWY0LTkyNjItMTE3OTkxNzUyMGVlIiwidCI6ImJkOGI4MTg0LTNhNjItNDY0NC04ZDFkLWVkZjliYmVkNDgxMCIsImMiOjN9"
            frameborder="0" allowFullScreen="true"></iframe> --}}

        <iframe title="MonthlyCost" width="99%" height="500px"
            src="https://app.powerbi.com/reportEmbed?reportId=9147031d-2911-4fb8-b04a-16abd0f0ce2b&autoAuth=true&ctid=a1f9fe87-d35d-4a23-ad73-6d612ea9e617"
            frameborder="0" allowFullScreen="true"></iframe>
    </div>



</x-backend.layouts.master>
