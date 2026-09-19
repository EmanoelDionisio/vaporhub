import { X, CheckCircle } from 'lucide-react';
import { useState } from 'react';

interface ResellerModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function ResellerModal({ isOpen, onClose }: ResellerModalProps) {
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    cpf: '',
    cnpj: '',
    whatsapp: ''
  });

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Simulate submission
    console.log('Form submitted:', formData);
    setIsSubmitted(true);
    // Reset after 3 seconds
    setTimeout(() => {
        setIsSubmitted(false);
        onClose();
        setFormData({ name: '', cpf: '', cnpj: '', whatsapp: '' });
    }, 3000);
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 md:p-8 animate-in zoom-in-95 duration-200">
        <button 
          onClick={onClose}
          className="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
        >
          <X className="size-5" />
        </button>

        {isSubmitted ? (
          <div className="flex flex-col items-center text-center py-8">
            <div className="size-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4">
              <CheckCircle className="size-8" />
            </div>
            <h3 className="text-2xl font-bold text-gray-900 mb-2">Sucesso!</h3>
            <p className="text-gray-600">
              Recebemos seus dados. Nossa equipe entrará em contato em breve pelo WhatsApp.
            </p>
          </div>
        ) : (
          <>
            <div className="text-center mb-6">
              <h3 className="text-2xl font-bold text-gray-900 mb-2">Seja um Revendedor</h3>
              <p className="text-gray-600 text-sm">
                Preencha os dados abaixo para receber nosso catálogo exclusivo e condições especiais.
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  required
                  value={formData.name}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                  placeholder="Seu nome"
                />
              </div>

              <div>
                <label htmlFor="cpf" className="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                <input
                  type="text"
                  id="cpf"
                  name="cpf"
                  required
                  value={formData.cpf}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                  placeholder="000.000.000-00"
                />
              </div>

              <div>
                <label htmlFor="cnpj" className="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                <input
                  type="text"
                  id="cnpj"
                  name="cnpj"
                  required
                  value={formData.cnpj}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                  placeholder="00.000.000/0000-00"
                />
              </div>

              <div>
                <label htmlFor="whatsapp" className="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                <input
                  type="tel"
                  id="whatsapp"
                  name="whatsapp"
                  required
                  value={formData.whatsapp}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                  placeholder="(00) 00000-0000"
                />
              </div>

              <button
                type="submit"
                className="w-full bg-primary hover:bg-primary-hover text-background font-bold py-3 px-4 rounded-lg shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 mt-2"
              >
                Enviar Solicitação
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  );
}
