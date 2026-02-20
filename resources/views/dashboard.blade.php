<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            {{-- Summary cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm font-medium">Total Documents</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalDocuments) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm font-medium">Projects</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalProjects) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm font-medium">Entities</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalEntities) }}</div>
                </div>
            </div>

            {{-- Documents per project --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-900">Documents per project</h3>
                </div>
                <div class="p-6">
                    @if($documentsPerProject->isEmpty())
                        <p class="text-gray-500">No projects with documents yet.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach($documentsPerProject as $project)
                                <li class="py-2 flex justify-between">
                                    <span class="font-medium">{{ $project->project_name }}</span>
                                    <span class="text-gray-500">{{ $project->project_number }} — {{ $project->documents_count }} docs</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Recent uploads --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Recent uploads</h3>
                    <a href="{{ route('documents.upload') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Upload</a>
                </div>
                <div class="p-6">
                    @if($recentDocuments->isEmpty())
                        <p class="text-gray-500">No documents yet. <a href="{{ route('documents.upload') }}" class="text-indigo-600 hover:underline">Upload PDFs</a></p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach($recentDocuments as $doc)
                                <li class="py-3 flex justify-between items-start gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-900 truncate">{{ $doc->file_name }}</div>
                                        <div class="text-sm text-gray-500">
                                            {{ $doc->project?->project_number ?? '-' }} / {{ $doc->discipline ?? '-' }} / {{ $doc->document_type ?? '-' }}
                                        </div>
                                    </div>
                                    <a href="{{ route('documents.download', $doc) }}" class="shrink-0 text-sm text-indigo-600 hover:text-indigo-800">Download</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
