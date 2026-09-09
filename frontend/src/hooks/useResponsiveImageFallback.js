import { useState } from 'react';

const isPositiveInteger = (value) => Number.isInteger(value) && value > 0;

const buildSrcSet = ({ masterUrl, masterWidth, variants }) => {
  if (variants.length === 0) return '';

  const candidates = [...variants];
  if (isPositiveInteger(masterWidth)) {
    const duplicateIndex = candidates.findIndex((candidate) => candidate.width === masterWidth);

    if (duplicateIndex === candidates.length - 1) {
      candidates[duplicateIndex] = { url: masterUrl, width: masterWidth };
    } else if (duplicateIndex !== -1 || masterWidth <= candidates.at(-1).width) {
      return '';
    } else {
      candidates.push({ url: masterUrl, width: masterWidth });
    }
  }

  return candidates
    .map((candidate) => `${candidate.url} ${candidate.width}w`)
    .join(', ');
};

const comparableUrl = (value) => {
  if (typeof value !== 'string' || value.trim() === '') return null;

  try {
    const url = new URL(value, window.location.href);
    if (
      !['http:', 'https:'].includes(url.protocol)
      || url.username !== ''
      || url.password !== ''
    ) {
      return null;
    }

    return url.href;
  } catch {
    return null;
  }
};

const selectedSourceIsMaster = (currentSrc, masterUrl) => {
  const selected = comparableUrl(currentSrc);
  const master = comparableUrl(masterUrl);

  return selected !== null && master !== null && selected === master;
};

export const useResponsiveImageFallback = ({ masterUrl, masterWidth, variants = [], sizes }) => {
  const srcSet = buildSrcSet({ masterUrl, masterWidth, variants });
  const identity = `${masterUrl}\n${srcSet}\n${sizes || ''}`;
  const [failure, setFailure] = useState({ identity: null, stage: null });
  const stage = failure.identity === identity
    ? failure.stage
    : (srcSet && sizes ? 'responsive' : 'master');

  const onError = (event) => {
    if (stage === 'responsive') {
      const failedMasterCandidate = selectedSourceIsMaster(
        event.currentTarget.currentSrc,
        masterUrl,
      );
      event.currentTarget.removeAttribute('srcset');
      event.currentTarget.removeAttribute('sizes');

      if (failedMasterCandidate) {
        setFailure({ identity, stage: 'fallback' });
        return;
      }

      event.currentTarget.src = masterUrl;
      setFailure({ identity, stage: 'master' });
      return;
    }

    if (stage === 'master') {
      setFailure({ identity, stage: 'fallback' });
    }
  };

  return {
    failed: stage === 'fallback',
    imageProps: {
      src: masterUrl,
      srcSet: stage === 'responsive' ? srcSet : undefined,
      sizes: stage === 'responsive' ? sizes : undefined,
      onError,
    },
  };
};
