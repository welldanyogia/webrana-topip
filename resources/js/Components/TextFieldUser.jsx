export default function TextFieldUser({form,handleChange}){
    return (
        <div className="relative">
            <input type="text" id={`hs-floating-gray-input-${form.id}`} className="peer p-4 block w-full bg-gray-100 border-transparent rounded-lg text-sm placeholder:text-transparent focus:border-lime-500 focus:ring-lime-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-transparent dark:text-neutral-400 dark:focus:ring-neutral-600
                                                    focus:pt-6
                                                    focus:pb-2
                                                    [&:not(:placeholder-shown)]:pt-6
                                                    [&:not(:placeholder-shown)]:pb-2
                                                    autofill:pt-6
                                                    autofill:pb-2"
                   placeholder={form.name} onChange={handleChange}/>
            <label htmlFor={`hs-floating-gray-input-${form.id}`} className="absolute top-0 start-0 p-4 h-full text-sm truncate pointer-events-none transition ease-in-out duration-100 border border-transparent  origin-[0_0] peer-disabled:opacity-50 peer-disabled:pointer-events-none
                                                              peer-focus:scale-90
                                                              peer-focus:translate-x-0.5
                                                              peer-focus:-translate-y-1.5
                                                              peer-focus:text-gray-500 dark:peer-focus:text-lime-500
                                                              peer-[:not(:placeholder-shown)]:scale-90
                                                              peer-[:not(:placeholder-shown)]:translate-x-0.5
                                                              peer-[:not(:placeholder-shown)]:-translate-y-1.5
                                                              peer-[:not(:placeholder-shown)]:text-lime-500 dark:peer-[:not(:placeholder-shown)]:text-lime-500 dark:text-lime-500">
                {form.name}
            </label>
        </div>
    )
}
