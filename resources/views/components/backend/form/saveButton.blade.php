<button type="submit"
    class="btn btn-sm btn-outline-warning">
    <span>
        {{-- <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <title>Save</title>
            <path d="M28 0h-28v32h32v-28l-4-4zM16 4h4v8h-4v-8zM28 28h-24v-24h2v10h18v-10h2.343l1.657 1.657v22.343z">
            </path>
        </svg> --}}

       <i class="bi bi-save-fill"></i> Save

    </span>
</button>

<style>
    .btn-outline-warning:hover {
        background: linear-gradient(45deg, #FCA311, #FFD3B6);
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
