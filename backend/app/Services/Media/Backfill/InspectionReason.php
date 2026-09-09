<?php

namespace App\Services\Media\Backfill;

enum InspectionReason: string
{
    case ObjectMissing = 'object_missing';
    case AccessDenied = 'access_denied';
    case Timeout = 'timeout';
    case TransportError = 'transport_error';
    case TruncatedRead = 'truncated_read';
    case InvalidJson = 'invalid_json';
    case SchemaViolation = 'schema_violation';
    case IdentityMismatch = 'identity_mismatch';
    case InvalidReference = 'invalid_reference';
    case InvalidImage = 'invalid_image';
    case UnsafeMaster = 'unsafe_master';
    case UnexpectedOrientation = 'unexpected_master_orientation';
    case UnsupportedMetadata = 'unsupported_master_metadata';
    case EncodingFailed = 'encoding_failed';
    case DescriptorMismatch = 'descriptor_mismatch';
    case IncompleteCandidates = 'incomplete_candidates';
    case ExistingVariantCollision = 'existing_variant_collision';
    case SharedIdentity = 'shared_identity';
    case MetadataMismatch = 'metadata_mismatch';
}
