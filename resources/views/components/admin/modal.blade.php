@props(['id'])
<div id="{{ $id }}" class="modal fade" tabindex="-1" role="dialog" data-modal><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">{{ $title ?? 'Confirmation' }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body">{{ $slot }}</div></div></div></div>
