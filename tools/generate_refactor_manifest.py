#!/usr/bin/env python3
import os
import json
import datetime
import re

root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
refactor_dir = os.path.join(root, 'docs', 'refactor')
session_notes_dir = os.path.join(refactor_dir, 'session-notes')
os.makedirs(session_notes_dir, exist_ok=True)

excluded_dirs = {'vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public/build', '.git', 'lang'}
excluded_files = {
    '.env', '.env.example', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
    'artisan', 'vite.config.js', 'phpunit.xml', '.gitignore', '.gitattributes', '.editorconfig',
    '.dockerignore', 'Dockerfile', 'compose.yaml', 'Caddyfile', 'LICENSE',
    'SECURITY.md', 'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'phpstan.neon', 'blackbox_mcp_settings.json',
    '.phpunit.result.cache', 'database.sqlite'
}

# Collect in-scope folders and files
folders_map = {}

# Preferred processing order from prompt
processing_order_prefixes = [
    'app/Enums',
    'app/Interfaces',
    'app/Traits',
    'app/Models',
    'app/DTOs',
    'app/ValueObjects',
    'app/Concerns',
    'app/Actions',
    'app/Domain',
    'app/Services',
    'app/Repositories',
    'app/Jobs',
    'app/Listeners',
    'app/Events',
    'app/Mail',
    'app/Notifications',
    'app/Observers',
    'app/Policies',
    'app/Exports',
    'app/OpenApi',
    'app/Http/Requests',
    'app/Http/Resources',
    'app/Http/Middleware',
    'app/Http/Controllers',
    'app/Console/Commands',
    'app/Providers',
    'app',
    'routes',
    'config',
    'database/factories',
    'database/seeders',
    'database/migrations',
    'database',
    'bootstrap',
    'resources/views',
    'resources/css',
    'resources/js',
    'resources/docs',
    'resources',
    'public',
    'docs',
    '.github',
    'tools',
    'tests'
]

for dirpath, dirnames, filenames in os.walk(root):
    rel_dir = os.path.relpath(dirpath, root)
    if rel_dir == '.':
        rel_dir = ''
    parts = rel_dir.split(os.sep) if rel_dir else []
    
    skip = False
    for p in parts:
        if p in ['vendor', 'node_modules', 'storage', '.git', 'lang']:
            skip = True
            break
    if rel_dir.startswith('bootstrap/cache') or rel_dir.startswith('public/build') or rel_dir.startswith('docs/refactor'):
        skip = True
    if skip:
        continue
    
    # filter files
    f_list = []
    for f in sorted(filenames):
        if f in excluded_files or f.startswith('.env.') or f.endswith('.pyc') or '__pycache__' in rel_dir:
            continue
        rel_file_path = os.path.normpath(os.path.join(rel_dir, f)) if rel_dir else f
        f_list.append(rel_file_path)
        
    if f_list or rel_dir in ['app', 'resources', 'routes', 'database', 'config', 'tests']:
        if rel_dir:
            folders_map[rel_dir] = f_list

# Sort folders according to the recommended processing order
def folder_sort_key(folder):
    for idx, prefix in enumerate(processing_order_prefixes):
        if folder == prefix or folder.startswith(prefix + '/'):
            return (idx, folder)
    return (len(processing_order_prefixes), folder)

sorted_folders = sorted(folders_map.keys(), key=folder_sort_key)

# Build manifest.json
manifest = {
    'generated_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'folders': {}
}

for folder in sorted_folders:
    files_dict = {}
    for fpath in folders_map[folder]:
        files_dict[fpath] = {
            'status': 'pending',
            'new_path': None,
            'notes': []
        }
    manifest['folders'][folder] = {
        'status': 'pending',
        'files': files_dict,
        'readme_status': 'pending'
    }

with open(os.path.join(refactor_dir, 'manifest.json'), 'w', encoding='utf-8') as f:
    json.dump(manifest, f, indent=2, ensure_ascii=False)

# Build progress.md
total_files = sum(len(fl) for fl in folders_map.values())
progress_lines = [
    '# Codebase Normalization & Documentation Progress',
    '',
    f'> Total in-scope folders: {len(folders_map)} | Total in-scope files: {total_files}',
    f'> Generated: {datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")}',
    '',
    '## Summary Checklist by Directory (Ordered by Dependency Hierarchy)',
    ''
]

for folder in sorted_folders:
    files = folders_map[folder]
    progress_lines.append(f'### `{folder}` ({len(files)} files) — PENDING (0/{len(files)})')
    for fpath in files:
        fname = os.path.basename(fpath)
        progress_lines.append(f'- [ ] `{fname}`')
    progress_lines.append('- Folder README: pending')
    progress_lines.append('')

progress_lines.append('## NEEDS REVIEW')
progress_lines.append('')
progress_lines.append('*(No items currently blocking. Escalate any ambiguous items here during execution.)*')
progress_lines.append('')

with open(os.path.join(refactor_dir, 'progress.md'), 'w', encoding='utf-8') as f:
    f.write('\n'.join(progress_lines))

print(f'Manifest and progress generated: {len(sorted_folders)} folders, {total_files} files.')
