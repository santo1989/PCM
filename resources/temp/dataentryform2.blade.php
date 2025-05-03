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
                            <table class="table table-bordered">
                                <tr>
                                    <th>KRA</th>
                                    <th>KPI</th>
                                    <th>Weight of KPIs</th>
                                    <th>Bench Mark / Target</th>
                                    <th>Achivement</th>
                                    <th>Sources of Informations</th>
                                    <th>Score (Out of 100)</th>
                                    <th>Weight Value</th>
                                    <th>Justification</th>
                                </tr>

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

                                        </select>


                                    </td>
                                    <td><input type="text" name="weight_of_kpis[]"
                                            class="form-control weight_of_kpis" /></td>
                                    <td><input type="text" name="bench_mark_target[]"
                                            class="form-control bench_mark_target" /></td>
                                    <td><input type="text" name="achivement[]" class="form-control achivement" />
                                    </td>
                                    <td><input type="text" name="sources_of_informations[]"
                                            class="form-control sources_of_informations" /></td>
                                    <td><input type="text" name="score[]" class="form-control score" /></td>
                                    <td><input type="text" name="weight_value[]" class="form-control weight_value" />
                                    </td>
                                    <td><input type="text" name="justification[]"
                                            class="form-control justification" /></td>
                                    <td><button type="button" name="add" id="add" class="btn btn-success">Add
                                            More</button></td>
                                </tr>

                            </table>

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
            var i = 1;
            $('#add').click(function() {
                i++;
              $('#row1').after('<tr id="row' + i + '">' +
                '<td>' +
                '<select name="kra[]" id="kra_id" class="form-control kra">' +
                '<option value="">Select KRA</option>' +
                @foreach ($kras as $kra)
                    '<option value="{{ $kra->id }}">{{ $kra->name }}</option>' +
                @endforeach
            '</select>' +
            '</td>' +
            '<td>' +
            '<select name="kpi[]" class="form-control kpi">' +
            '<option value="">Select KPI</option>' +
            '</select>' +
            '</td>' + +
                    '<td><input type="text" name="weight_of_kpis[]" class="form-control weight_of_kpis" /></td>' +
                    '<td><input type="text" name="bench_mark_target[]" class="form-control bench_mark_target" /></td>' +
                    '<td><input type="text" name="achivement[]" class="form-control achivement" /></td>' +
                    '<td><input type="text" name="sources_of_informations[]" class="form-control sources_of_informations" /></td>' +
                    '<td><input type="text" name="score[]" class="form-control score" /></td>' +
                    '<td><input type="text" name="weight_value[]" class="form-control weight_value" /></td>' +
                    '<td><input type="text" name="justification[]" class="form-control justification" /></td>' +
                    '<td><button type="button" name="remove" id="' + i +
                    '" class="btn btn-danger btn_remove">X</button></td></tr>'
                );
            });
            $(document).on('click', '.btn_remove', function() {
                var button_id = $(this).attr("id");
                $('#row' + button_id + '').remove();
            });
            $('#submit').click(function() {
                $.ajax({
                    url: "{route('submit-form')}",
                    method: "POST",
                    data: $('#add_name').serialize(),
                    success: function(data) {
                        alert(data);
                        $('#add_name')[0].reset();
                    }
                });
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('#kra_id').on('change', function() {
                var kra_id = $(this).val();
                console.log(kra_id) 
                    $.ajax({
                        url: "{{ route('getKPI') }}",
                        type: "GET",
                        data: {
                            kra_id: kra_id,
                        },
                        success: function(data) {
                            console.log(data);
                            $('#kpi_id').empty();
                            $('#kpi_id').append('<option value="">Select KPI</option>');
                            $.each(data, function(key, value) {
                                $('#kpi_id').append('<option value="' + value.id +
                                    '">' + value.name + '</option>');
                            });
                        }
                    });
                
            });
        });
    </script>



</x-backend.layouts.master>
