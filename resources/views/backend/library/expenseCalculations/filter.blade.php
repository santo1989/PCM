 
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Support Issue Monitoring Softwear from NTG, MIS Department" />
    <meta name="author" content="Md. Hasibul Islam Santo, MIS, NTG" />
    <title> {{ $pageTitle ?? 'NTG-Support-Issue' }} </title>

    <!-- <link href="css/styles.css" rel="stylesheet" /> -->

    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- bootstrap 5 cdn  -->

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.1/css/bootstrap.min.css">
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.1/js/bootstrap.min.js"></script>


    <!-- font-awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    <!-- Bootstrap core icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />



    <!-- sweetalert2 cdn-->

    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <!-- DataTable -->

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />

    <!-- Custom CSS -->

    <link href="{{ asset('ui/backend/css/styles.css') }}" rel="stylesheet" />

    <!-- Push Notification -->

    <script src="{{ asset('js/push.min.js') }}"></script>

</head>

<body class="sb-nav-fixed">

    <x-backend.layouts.partials.top_bar />
 
    <section class="container-fluid pt-4">
        <div class="pt-4">

            <div class="row">
                <div class="col-md-4 col-sm-12 col-xl-4">
                    <div class="card" style="background-color: #40c47c;">
                        {{-- style="width: 100vw; overflow-x: scroll;" --}}

                        <div class="card-header" style="background: rgba(0, 0, 0, 0.4); color: #f1f1f1; ">

                            <form action="{{ route('expenseCalculations.filter') }}" method="GET" id="filterForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 col-sm-12"> 
                                                <div class="form-group">
                                                    <ul>
                                                        <label for="types">Types</label>
                                                        
                                                            @php
                                                                $types = App\Models\ExpenseCalculation::select('types')->distinct()->get();
                                                            @endphp
                                                            <ul style="list-style-type: none;">
                                                            @foreach ($types as $type)
                                                                
                                                                <li><input type="checkbox" name="types[]" value="{{ $type->types }}">{{ $type->types }}</li>
                                                                
                                                            @endforeach 
                                                            </ul>
                                                    </ul>
                                                </div>

                                                <div class="form-group">
                                                    <ul>
                                                        <label for="category_id">Category</label>
                                                        
                                                            @php
                                                                $categories = App\Models\ExpenseCalculation::select('category_id')->distinct()->get();
                                                            @endphp
                                                            <ul style="list-style-type: none;">
                                                            @foreach ($categories as $category)
                                                                
                                                                <li><input type="checkbox" name="category_id[]" value="{{ $category->category_id }}">
                                                                    @php
                                                                        $category = App\Models\Category::find($category->category_id) == null ? '' : App\Models\Category::find($category->category_id)->name;
                                                                    @endphp
                                                                    {{ $category }}
                                                                </li>
                                                                
                                                            @endforeach 
                                                            </ul>
                                                    </ul> 
                                                </div>

                                                <div class="form-group">
                                                    <td>Date:</td>
                                                    <td>
                                                        <input type="date" name="entry_date_start"
                                                            id="entry_date_start" class="form-control">
                                                    </td>
                                                    <td>-</td>
                                                    <td>
                                                        <input type="date" name="entry_date_end" id="entry_date_end"
                                                            class="form-control">
                                                    </td>
                                                </div>  
                                    </div>
                                </div>

                            </form>


                        </div>

                    </div>
                </div>
                <div class="col-md-8 col-sm-12 col-xl-8">
                <!-- /.card2 -->
                <div class="card"> 
                    <table class="table table-bordered table-hover" id="myTable">
                        <thead>
                            <tr> 
                                <th>Sl#</th> 
                                {{-- <th>Date</th>  --}}
                                {{-- <th>Name</th> --}}
                                <th>Category Name</th>
                                <th>Category Types</th>
                                <th>Amount</th> 
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 1;
                            @endphp
                            @forelse ($expenseCalculations as $cash)
                                <tr>  
                                    <td>{{ $i++ }}
                                    </td> 
                                     {{-- <td>{{ $cash->date ? \Carbon\Carbon::parse($cash->date)->format('d-M-Y') : '' }} --}}
                                    </td>
                                    {{-- <td>{{ $cash->name }}</td> --}}
                                    <td>{{ $cash->types }}</td>
                                    <td>
                                        @php
                                            $category = App\Models\Category::find($cash->category_id) == null ? '' : App\Models\Category::find($cash->category_id)->name;
                                        @endphp
                                        {{ $category }}
                                    </td>
                                    <td>{{ $cash->total_amount }}</td>
                                     
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="alert alert-danger">
                                            No Data Found
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>  
                </div>
                <!-- /.card2 -->
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Trigger form submission on checkbox change
            $('input[type="checkbox"]').on('change', function() {
                $('#filterForm').submit(); // Submit form
            });

            // Handle form submission
            $('#filterForm').on('submit', function(e) {
                e.preventDefault(); // Prevent form submission
                
                var formData = $(this).serialize(); // Serialize form data

                $.ajax({
                    url: "{{ route('expenseCalculations.filter') }}",
                    type: "GET",
                    data: formData,
                    success: function(response) {
                        $('#myTable tbody').html(response); // Update table body with filtered data
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            });
        });
    </script>

<!-- Core theme JS-->
    <script src="{{ asset('ui/backend/js/scripts.js') }}"></script>

    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>



    <!-- DataTable JS -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
    <script src="{{ asset('ui/backend/js/datatables-simple-demo.js') }}"></script>

    <!-- Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

</body>

</html>

 