import { useEffect, useRef } from 'react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { go } from '../utils/router.js';

export function CreatePlan({ user, notify }) {
  const creatingRef = useRef(false);

  useEffect(() => {
    if (creatingRef.current) return;
    creatingRef.current = true;

    async function createPlan() {
      try {
        const planData = await api.plans();
        const existingPlan = planData.plans?.[0];
        if (existingPlan) {
          go(`/plan?id=${existingPlan.id}&edit=1`);
          return;
        }

        const data = await api.createPlan({
          assigned_user_id: user.id,
          name: user.full_name
        });
        notify('Scheda creata');
        go(`/plan?id=${data.id}&edit=1`);
      } catch (err) {
        notify(err.message, 'error');
        go('/workouts');
      }
    }

    createPlan();
  }, [notify, user.full_name, user.id]);

  return (
    <section className="page">
      <Loading label="Creazione scheda" />
    </section>
  );
}
