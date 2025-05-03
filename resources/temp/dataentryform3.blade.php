<x-backend.layouts.master>
    <x-slot name="pageTitle">
        KPI Data Entry Form
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> KPI Data Entry Form </x-slot>

            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('supervisor_assigns.index') }}">KPI Data Entry Form </a></li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <section class="content">
        <div class="container-fluid">

            @if (session('message'))
                <div class="alert alert-success">
                    <span class="close" data-dismiss="alert">&times;</span>
                    <strong>{{ session('message') }}.</strong>
                </div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">

                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            {{-- supervisor_assign Table goes here --}}
                            <form method="POST" action="{{ route('submit-form') }}">
                                @csrf
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>KRA</th>
                                            <th>KPI</th>
                                            <th>Weight of KPIs</th>
                                            {{-- <th>Benchmark Target</th> --}}
                                            <th>Achievement</th>
                                            <th>Sources of Information</th>
                                            {{-- <th>Score</th> --}}
                                            {{-- <th>Weight Value</th> --}}
                                            <th>Justification</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody">
                                        <tr id="row1">
                                            <td>
                                                <select name="kra[]" id="kra_id" class="form-control kra">
                                                    <option value="">Select KRA</option>
                                                    @foreach ($kras as $kra)
                                                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="kpi[]" id="kpi_id" class="form-control kpi">
                                                    <option value="">Select KPI</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="weight_of_kpis[]"
                                                    class="form-control weight_of_kpis" /></td>
                                            {{-- <td><input type="text" name="bench_mark_target[]"
                                                    class="form-control bench_mark_target" /></td> --}}
                                            <td><input type="text" name="achivement[]"
                                                    class="form-control achivement" /></td>
                                            <td><input type="text" name="sources_of_informations[]"
                                                    class="form-control sources_of_informations" /></td>
                                            {{-- <td><input type="text" name="score[]" class="form-control score" /></td> --}}
                                            {{-- <td><input type="text" name="weight_value[]"
                                                    class="form-control weight_value" /></td> --}}
                                            <td><input type="text" name="justification[]"
                                                    class="form-control justification" /></td>
                                            <td><button type="button" name="add" id="add"
                                                    class="btn btn-success">Add More</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </form>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->


                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <script>
        $(document).ready(function() {
                    // Add new row
                    $("#add").click(function() {
                            var rowId = Math.floor(Math.random() * 1000000); // Generate random row id
                            var row = $("#row1").clone(); // Clone the first row
                            row.attr("id", "row" + rowId); // Set the new row id
                            row.find("select.kra").attr("name", "kra[]").attr("id", "kra_" +
                            rowId); // Update select's name and id
                            row.find("select.kpi").attr("name", "kpi[]").attr("id", "kpi_" +
                            rowId); // Update select's name and id
                            row.find("input.weight_of_kpis").attr("name", "weight_of_kpis[]"); // Update input's name
                            row.find("input.bench_mark_target").attr("name",
                            "bench_mark_target[]"); // Update input's name
                            row.find("input.achivement").attr("name", "achivement[]"); // Update input's name
                            row.find("input.sources_of_informations ").attr("name ","sources_of_informations[]"); // Update input's name
                                row.find("input.score").attr("name", "score[]"); // Update input's name
                                row.find("input.weight_value").attr("name",
                                "weight_value[]"); // Update input's name
                                row.find("input.justification").attr("name",
                                "justification[]"); // Update input's name
                                row.find("#add").remove(); // Remove the add button from the new row
                                $("#tableBody").append(row); // Add the new row to the table body
                                // Update the KPI dropdown when KRA is selected
                                $("select.kra").change(function() {
                                    var kraId = $(this).val();
                                    var kpiDropdown = $(this).parent().next().find("select.kpi");

                                    // Clear the KPI dropdown
                                    kpiDropdown.empty();
                                    kpiDropdown.append("<option value=''>Select KPI</option>");

                                    // Load the KPIs for the selected KRA using AJAX
                                    $.ajax({
                                        type: "GET",
                                        url: "{{ route('getKPI') }}",
                                        data: {
                                            kra_id: kraId
                                        },
                                        dataType: "json",
                                        success: function(response) {
                                            $.each(response, function(key, kpi) {
                                                kpiDropdown.append("<option value='" + kpi
                                                    .id + "'>" + kpi.name + "</option>");
                                            });
                                        },
                                        error: function(xhr, status, error) {
                                            console.log(xhr.responseText);
                                        },
                                    });
                                });
                            });
                    });
    </script>





</x-backend.layouts.master>
