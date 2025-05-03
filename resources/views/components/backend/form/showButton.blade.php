<button type="submit"
    class="btn btn-sm btn-outline-success">
    <span>
        {{-- <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <title>Show</title>
            <path
                d="M16 6c-6.979 0-13.028 4.064-16 10 2.972 5.936 9.021 10 16 10s13.027-4.064 16-10c-2.972-5.936-9.021-10-16-10zM23.889 11.303c1.88 1.199 3.473 2.805 4.67 4.697-1.197 1.891-2.79 3.498-4.67 4.697-2.362 1.507-5.090 2.303-7.889 2.303s-5.527-0.796-7.889-2.303c-1.88-1.199-3.473-2.805-4.67-4.697 1.197-1.891 2.79-3.498 4.67-4.697 0.122-0.078 0.246-0.154 0.371-0.228-0.311 0.854-0.482 1.776-0.482 2.737 0 4.418 3.582 8 8 8s8-3.582 8-8c0-0.962-0.17-1.883-0.482-2.737 0.124 0.074 0.248 0.15 0.371 0.228v0zM16 13c0 1.657-1.343 3-3 3s-3-1.343-3-3 1.343-3 3-3 3 1.343 3 3z">
            </path>
        </svg> --}}
         <i class="bi bi-eye-fill"></i>Show
    </span>
</button>

<style>
    .btn-outline-warning:hover {
        background: linear-gradient(45deg, #FCA311, #FFD3B6);
        color: #fff;
    }

    .btn-outline-secondary:hover {
        background: linear-gradient(45deg, #545454, #808080);
        color: #fff;
    }

    .btn-outline-success:hover {
        background: linear-gradient(45deg, #28A745, #6FCE9C);
        color: #fff;
    }

    .btn-outline-danger:hover {
        background: linear-gradient(45deg, #DC3545, #FFA0A0);
        color: #fff;
    }

    .btn-outline-info:hover {
        background: linear-gradient(45deg, #17A2B8, #9ADCEC);
        color: #fff;
    }

    .btn:hover span {
        animation: pulse 1s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }
</style>
