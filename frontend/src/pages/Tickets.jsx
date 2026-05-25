import { useRef, useState } from 'react';
import { ImagePlus, Send, TicketCheck, X } from 'lucide-react';
import { api } from '../api.js';

export function Tickets({ notify }) {
  const [message, setMessage] = useState('');
  const [image, setImage] = useState(null);
  const [loading, setLoading] = useState(false);
  const fileInputRef = useRef(null);

  function chooseImage(event) {
    const file = event.target.files?.[0] || null;
    if (!file) {
      setImage(null);
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      notify('Formato non supportato. Usa JPG, PNG o WebP.', 'error');
      event.target.value = '';
      return;
    }
    if (file.size > 4 * 1024 * 1024) {
      notify('Immagine troppo grande. Massimo 4MB.', 'error');
      event.target.value = '';
      return;
    }
    setImage(file);
  }

  async function submit(event) {
    event.preventDefault();
    const text = message.trim();
    if (text.length < 10) {
      notify('Descrivi il problema con almeno 10 caratteri.', 'error');
      return;
    }

    setLoading(true);
    try {
      const data = await api.createTicket({ message: text, image });
      notify(data.message || 'Ticket inviato');
      setMessage('');
      setImage(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  return (
    <section className="page">
      <div className="page-title ticket-title">
        <span className="ticket-title-icon"><TicketCheck size={22} /></span>
        <div>
          <h2>Ticket</h2>
          <p>Segnala un malfunzionamento del sito.</p>
        </div>
      </div>

      <form className="ticket-panel" onSubmit={submit}>
        <label className="ticket-field">
          <span>Descrizione problema</span>
          <textarea
            value={message}
            onChange={(event) => setMessage(event.target.value)}
            placeholder="Scrivi cosa non funziona, in quale pagina succede e cosa stavi facendo."
            rows={8}
            maxLength={3000}
          />
          <small>{message.trim().length}/3000 caratteri</small>
        </label>

        <div className="ticket-upload">
          <label className="ghost ticket-upload-button">
            <ImagePlus size={18} />
            Aggiungi immagine
            <input ref={fileInputRef} type="file" accept="image/png,image/jpeg,image/webp" onChange={chooseImage} />
          </label>
          {image && (
            <div className="ticket-file">
              <span>{image.name}</span>
              <button className="icon-button" type="button" aria-label="Rimuovi immagine" onClick={() => {
                setImage(null);
                if (fileInputRef.current) {
                  fileInputRef.current.value = '';
                }
              }}>
                <X size={17} />
              </button>
            </div>
          )}
        </div>

        <button className="primary ticket-submit" disabled={loading || message.trim().length < 10}>
          <Send size={18} /> {loading ? 'Invio...' : 'Invia ticket'}
        </button>
      </form>
    </section>
  );
}
