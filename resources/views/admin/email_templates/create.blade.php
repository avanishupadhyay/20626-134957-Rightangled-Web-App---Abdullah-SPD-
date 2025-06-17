@extends('admin.layouts.app')

@section('content')
    <style>
        .ck .ck-powered-by {
            display: none;
        }
        label{
            font-weight: 600;
        }
    </style>
    <div class="card p-4">

        <h2>Create Email Template</h2><br>
        <form method="POST" action="{{ route('admin.email-templates.store') }}">
            @csrf
            <div>
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" value="{{ $template['subject'] ?? '' }}" required>
            </div>
            <br>
            <div>
                <label>Body</label>
                <textarea name="body" id="editor" class="form-control" rows="10">{!! $template['body'] ?? '' !!}</textarea>
                <br>
                <p style="font-weight: 600">Available Shortcodes:
                    <code>{name}</code>,
                    <code>{email}</code>,
                    <code>{gphc_number}</code>,
                    <code>{signature_image}</code>,
                    <code>{role}</code>
                </p>
            </div>

            <button type="submit" class="btn btn-primary mt-2">Save</button>
        </form>
    </div>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('#editor'))
            .catch(error => {
                console.error(error);
            });
    </script>
@endsection
