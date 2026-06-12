using System;
using System.Diagnostics;
using System.Threading;

namespace SRMSSLauncher
{
    class Program
    {
        static void Main(string[] args)
        {
            Console.Title = "SRMSS Launcher";
            Console.ForegroundColor = ConsoleColor.Cyan;
            
            Console.WriteLine("Welcome to..........");
            Thread.Sleep(1000);
            
            Console.ForegroundColor = ConsoleColor.White;
            Console.WriteLine("Smart Route Management and Scheduling System (SRMSS)");
            Thread.Sleep(1000);
            
            Console.ForegroundColor = ConsoleColor.Green;
            Console.WriteLine("Start..............");
            Thread.Sleep(1000);

            try
            {
                // Try opening the default browser to the system
                Process.Start(new ProcessStartInfo("http://localhost/SRMSS/") { UseShellExecute = true });
                Console.WriteLine("System launched in your default web browser.");
            }
            catch (Exception ex)
            {
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine("Failed to open browser automatically. Please visit http://localhost/SRMSS/");
            }

            Console.ForegroundColor = ConsoleColor.Gray;
            Console.WriteLine("\nPress any key to exit...");
            Console.ReadKey();
        }
    }
}
