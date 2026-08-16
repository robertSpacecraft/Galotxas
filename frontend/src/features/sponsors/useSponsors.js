import { useEffect, useState } from 'react';
import { sponsorService } from './sponsorService';

const initialState = {
  sponsors: [],
  status: 'loading',
};

export const useSponsors = () => {
  const [state, setState] = useState(initialState);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;

    sponsorService.getAll({ signal: controller.signal }).then(
      (sponsors) => {
        if (!active) return;

        setState({
          sponsors,
          status: sponsors.length === 0 ? 'empty' : 'content',
        });
      },
      () => {
        if (!active) return;

        setState({
          sponsors: [],
          status: 'error',
        });
      },
    );

    return () => {
      active = false;
      controller.abort();
    };
  }, []);

  return state;
};
