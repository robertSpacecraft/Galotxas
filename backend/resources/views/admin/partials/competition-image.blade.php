@inject('competitionImages', 'App\Services\CompetitionImageService')

<div class="col-12">
    <label for="image" class="form-label">Imagen de portada</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
           class="form-control @error('image') is-invalid @enderror" aria-describedby="image-help">
    @error('image')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div id="image-help" class="form-text">
        JPEG/PNG/WebP, hasta {{ number_format(config('media.profiles.banner.input_max_kb') / 1024, 0) }} MB.
        El backend adapta automáticamente dimensiones y peso dentro de los límites permitidos.
    </div>

    @if ($entity->exists && $entity->image_path !== null)
        @if ($competitionImages->isManaged($entity->image_path))
            <img src="{{ route('admin.'.$entity->getTable().'.image', $entity) }}"
                 alt="Imagen de portada actual" class="img-thumbnail mt-3" style="max-height: 240px;">
        @endif
        <div class="form-check mt-2">
            <input type="checkbox" id="remove_image" name="remove_image" value="1"
                   class="form-check-input @error('remove_image') is-invalid @enderror"
                   @checked(old('remove_image', false))>
            <label for="remove_image" class="form-check-label">Retirar imagen</label>
            @error('remove_image')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
