@if($errors->any())
    <div class="alert alert-danger mb-4">
        <x-icon name="circle-alert" class="mt-0.5 h-4 w-4 shrink-0" />
        <div class="min-w-0">
            <p class="font-semibold">Please check the following</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
