<button type="submit"
    class="btn btn-sm btn-outline-secondary">
    <span>
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <title>plus</title>
            <path
                d="M31 12h-11v-11c0-0.552-0.448-1-1-1h-6c-0.552 0-1 0.448-1 1v11h-11c-0.552 0-1 0.448-1 1v6c0 0.552 0.448 1 1 1h11v11c0 0.552 0.448 1 1 1h6c0.552 0 1-0.448 1-1v-11h11c0.552 0 1-0.448 1-1v-6c0-0.552-0.448-1-1-1z">
            </path>
        </svg>
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
